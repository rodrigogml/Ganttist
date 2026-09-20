<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentStorageContract;
use App\Jobs\Documents\ProcessDocumentDerivation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DocumentRevisionService
{
    public function __construct(private readonly DocumentStorageContract $storage) {}

    /** @param list<string> $tagIds */
    public function createDocument(string $projectId, string $userId, string $name, string $label, UploadedFile $file, array $tagIds): object
    {
        $documentId = (string) Str::ulid();
        $revisionId = (string) Str::ulid();
        $stored = $this->storage->storeUpload($projectId, $documentId, $revisionId, $file);

        try {
            DB::transaction(function () use ($projectId, $userId, $name, $label, $tagIds, $documentId, $revisionId, $stored): void {
                $now = now();
                DB::table('project_documents')->insert([
                    'id' => $documentId, 'project_id' => $projectId, 'name' => trim($name), 'kind' => 'manual',
                    'current_revision_id' => $revisionId, 'created_by_user_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->insertRevision($revisionId, $documentId, 1, $label, $userId, $stored);
                $this->syncTags($projectId, $documentId, $tagIds);
                DB::table('projects')->where('id', $projectId)->update(['updated_at' => $now]);
            });
        } catch (Throwable $exception) {
            $this->storage->delete($stored['disk'], $stored['key']);
            throw $exception;
        }

        Log::info('document.created', ['project_id' => $projectId, 'document_id' => $documentId, 'revision_id' => $revisionId, 'user_id' => $userId]);

        return DB::table('project_documents')->where('id', $documentId)->firstOrFail();
    }

    public function addRevision(string $projectId, string $documentId, string $userId, string $label, UploadedFile $file): object
    {
        $revisionId = (string) Str::ulid();
        $stored = $this->storage->storeUpload($projectId, $documentId, $revisionId, $file);

        try {
            DB::transaction(function () use ($projectId, $documentId, $userId, $label, $revisionId, $stored): void {
                $document = DB::table('project_documents')->where('id', $documentId)->where('project_id', $projectId)->lockForUpdate()->firstOrFail();
                abort_if($document->archived_at !== null, 409, 'Não é possível revisar um documento arquivado.');
                abort_if(DB::table('project_document_revisions')->where('document_id', $documentId)->where('sha256', $stored['sha256'])->exists(), 409, 'Este arquivo já existe no histórico do documento.');
                $sequence = ((int) DB::table('project_document_revisions')->where('document_id', $documentId)->max('revision_sequence')) + 1;
                $this->insertRevision($revisionId, $documentId, $sequence, $label, $userId, $stored);
                DB::table('project_documents')->where('id', $documentId)->update(['current_revision_id' => $revisionId, 'updated_at' => now()]);
                DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
                DB::afterCommit(fn () => $this->dispatchAutomaticDerivations($documentId, $revisionId));
            });
        } catch (Throwable $exception) {
            $this->storage->delete($stored['disk'], $stored['key']);
            throw $exception;
        }

        Log::info('document.revision.created', ['project_id' => $projectId, 'document_id' => $documentId, 'revision_id' => $revisionId, 'user_id' => $userId]);

        return DB::table('project_document_revisions')->where('id', $revisionId)->firstOrFail();
    }

    /** @param array{disk:string,key:string,size:int,sha256:string,mime:string,original_name:string} $stored */
    private function insertRevision(string $id, string $documentId, int $sequence, string $label, ?string $userId, array $stored): void
    {
        DB::table('project_document_revisions')->insert([
            'id' => $id, 'document_id' => $documentId, 'revision_sequence' => $sequence, 'revision_label' => trim($label),
            'mime_type' => $stored['mime'], 'original_filename' => $stored['original_name'], 'storage_disk' => $stored['disk'],
            'storage_key' => $stored['key'], 'size_bytes' => $stored['size'], 'sha256' => $stored['sha256'],
            'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param list<string> $tagIds */
    public function syncTags(string $projectId, string $documentId, array $tagIds): void
    {
        $tagIds = array_values(array_unique($tagIds));
        $valid = DB::table('project_tags')->where('project_id', $projectId)->whereNull('archived_at')->whereIn('id', $tagIds)->pluck('id')->all();
        abort_unless(count($valid) === count($tagIds), 422, 'Uma ou mais tags não pertencem ao projeto.');
        DB::table('project_document_tag')->where('document_id', $documentId)->delete();
        if ($valid !== []) {
            DB::table('project_document_tag')->insert(array_map(fn (string $tagId): array => ['document_id' => $documentId, 'tag_id' => $tagId], $valid));
        }
    }

    private function dispatchAutomaticDerivations(string $documentId, string $sourceRevisionId): void
    {
        DB::table('project_document_derivations')
            ->where('source_document_id', $documentId)->where('active', true)->where('auto_regenerate', true)
            ->get()
            ->each(function (object $derivation) use ($sourceRevisionId): void {
                $runId = $this->ensureRun($derivation, $sourceRevisionId);
                try {
                    ProcessDocumentDerivation::dispatch($runId)->onConnection('documents')->onQueue('documents');
                } catch (Throwable $exception) {
                    Log::error('document.derivation.dispatch_failed', [
                        'run_id' => $runId, 'derivation_id' => $derivation->id, 'exception' => $exception::class,
                    ]);
                }
            });
    }

    public function ensureRun(object $derivation, string $sourceRevisionId): string
    {
        $id = (string) Str::ulid();
        DB::table('project_document_derivation_runs')->insertOrIgnore([
            'id' => $id, 'derivation_id' => $derivation->id, 'source_revision_id' => $sourceRevisionId,
            'definition_version' => $derivation->definition_version, 'status' => 'queued', 'attempt_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (string) DB::table('project_document_derivation_runs')
            ->where('derivation_id', $derivation->id)->where('source_revision_id', $sourceRevisionId)
            ->where('definition_version', $derivation->definition_version)->value('id');
    }
}
