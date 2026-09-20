<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDerivationsRequest;
use App\Http\Requests\Documents\UpdateDerivationRequest;
use App\Jobs\Documents\ProcessDocumentDerivation;
use App\Services\Documents\DocumentRevisionService;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DocumentDerivationController extends Controller
{
    public function __construct(private readonly ProjectAccess $access, private readonly DocumentRevisionService $revisions) {}

    public function index(Request $request, string $projectId, string $documentId): JsonResponse
    {
        $this->access->document($request->user(), $projectId, $documentId);
        $rows = DB::table('project_document_derivations')->where('project_id', $projectId)
            ->where(fn ($query) => $query->where('source_document_id', $documentId)->orWhere('target_document_id', $documentId))
            ->orderByDesc('created_at')->get()->map(fn (object $row): array => $this->present($row))->all();

        return response()->json(['data' => $rows]);
    }

    public function store(StoreDerivationsRequest $request, string $projectId, string $sourceDocumentId): JsonResponse
    {
        $source = $this->access->document($request->user(), $projectId, $sourceDocumentId, true);
        abort_if($source->archived_at !== null || ! $source->current_revision_id, 409, 'A origem precisa possuir uma revisão atual.');
        $sourceRevision = DB::table('project_document_revisions')->where('id', $source->current_revision_id)->firstOrFail();
        abort_unless($sourceRevision->mime_type === 'application/pdf', 422, 'Apenas PDFs podem ser recortados nesta versão.');
        $data = $request->validated();
        $created = DB::transaction(function () use ($data, $projectId, $sourceDocumentId, $sourceRevision, $request): array {
            $created = [];
            foreach ($data['items'] as $item) {
                $documentId = (string) Str::ulid();
                $derivationId = (string) Str::ulid();
                $configuration = $this->configuration((int) $item['page'], $item['rectNormalized']);
                $configurationJson = json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                DB::table('project_documents')->insert([
                    'id' => $documentId, 'project_id' => $projectId, 'name' => trim($item['name']), 'kind' => 'derived',
                    'created_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('project_document_derivations')->insert([
                    'id' => $derivationId, 'project_id' => $projectId, 'source_document_id' => $sourceDocumentId, 'target_document_id' => $documentId,
                    'processor' => 'pdf.crop.region', 'processor_version' => 1, 'definition_version' => 1,
                    'configuration' => $configurationJson, 'configuration_hash' => hash('sha256', $configurationJson),
                    'auto_regenerate' => $item['autoRegenerate'] ?? true, 'active' => true, 'created_by_user_id' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->revisions->syncTags($projectId, $documentId, $item['tagIds'] ?? []);
                $derivation = DB::table('project_document_derivations')->where('id', $derivationId)->firstOrFail();
                $runId = $this->revisions->ensureRun($derivation, $sourceRevision->id);
                $created[] = ['documentId' => $documentId, 'derivationId' => $derivationId, 'runId' => $runId];
            }
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
            DB::afterCommit(function () use ($created): void {
                foreach ($created as $item) {
                    try {
                        ProcessDocumentDerivation::dispatch($item['runId'])->onConnection('documents')->onQueue('documents');
                    } catch (Throwable $exception) {
                        Log::error('document.derivation.dispatch_failed', [
                            'run_id' => $item['runId'], 'derivation_id' => $item['derivationId'], 'exception' => $exception::class,
                        ]);
                    }
                }
            });

            return $created;
        });
        foreach ($created as $item) {
            Log::info('document.derivation.created', [
                'project_id' => $projectId, 'source_document_id' => $sourceDocumentId,
                'source_revision_id' => $sourceRevision->id, 'target_document_id' => $item['documentId'],
                'derivation_id' => $item['derivationId'], 'run_id' => $item['runId'], 'processor' => 'pdf.crop.region', 'processor_version' => 1,
            ]);
        }

        return response()->json(['data' => $created], 202);
    }

    public function show(Request $request, string $projectId, string $derivationId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $row = DB::table('project_document_derivations')->where('id', $derivationId)->where('project_id', $projectId)->first() ?? abort(404);

        return response()->json(['data' => $this->present($row)]);
    }

    public function update(UpdateDerivationRequest $request, string $projectId, string $derivationId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $row = DB::table('project_document_derivations')->where('id', $derivationId)->where('project_id', $projectId)->first() ?? abort(404);
        $data = $request->validated();
        $configuration = null;
        if (isset($data['rectNormalized'])) {
            $configuration = $this->configuration((int) $data['page'], $data['rectNormalized']);
        }
        DB::transaction(function () use ($row, $data, $configuration): void {
            $changes = array_filter([
                'auto_regenerate' => $data['autoRegenerate'] ?? null, 'active' => $data['active'] ?? null, 'updated_at' => now(),
            ], fn ($value) => $value !== null);
            if ($configuration !== null) {
                $configurationJson = json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $changes += ['configuration' => $configurationJson, 'configuration_hash' => hash('sha256', $configurationJson)];
                $changes['definition_version'] = ((int) $row->definition_version) + 1;
            }
            DB::table('project_document_derivations')->where('id', $row->id)->update($changes);
        });
        if ($configuration !== null) {
            $updated = DB::table('project_document_derivations')->where('id', $row->id)->firstOrFail();
            $sourceRevisionId = DB::table('project_documents')->where('id', $updated->source_document_id)->value('current_revision_id');
            if (is_string($sourceRevisionId) && (bool) $updated->active) {
                $runId = $this->revisions->ensureRun($updated, $sourceRevisionId);
                ProcessDocumentDerivation::dispatch($runId);
            }
        }
        Log::info('document.derivation.updated', [
            'project_id' => $projectId, 'derivation_id' => $derivationId,
            'configuration_changed' => $configuration !== null,
            'auto_regenerate' => $data['autoRegenerate'] ?? $row->auto_regenerate,
            'active' => $data['active'] ?? $row->active,
        ]);

        return $this->show($request, $projectId, $derivationId);
    }

    public function regenerate(Request $request, string $projectId, string $derivationId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $derivation = DB::table('project_document_derivations')->where('id', $derivationId)->where('project_id', $projectId)->first() ?? abort(404);
        abort_unless((bool) $derivation->active, 409, 'Derivação inativa.');
        $sourceRevisionId = DB::table('project_documents')->where('id', $derivation->source_document_id)->value('current_revision_id');
        abort_unless(is_string($sourceRevisionId), 409, 'A origem não possui revisão atual.');
        $runId = $this->revisions->ensureRun($derivation, $sourceRevisionId);
        $run = DB::table('project_document_derivation_runs')->where('id', $runId)->firstOrFail();
        $status = $run->status;
        if ($status === 'failed') {
            DB::table('project_document_derivation_runs')->where('id', $runId)->update(['status' => 'queued', 'error_code' => null, 'error_message' => null, 'updated_at' => now()]);
            $status = 'queued';
        }
        if ($run->status !== 'succeeded') {
            ProcessDocumentDerivation::dispatch($runId)->onConnection('documents')->onQueue('documents');
        }
        Log::info('document.derivation.regenerate_requested', [
            'project_id' => $projectId, 'derivation_id' => $derivationId,
            'source_revision_id' => $sourceRevisionId, 'run_id' => $runId, 'previous_status' => $run->status,
        ]);

        return response()->json(['data' => ['runId' => $runId, 'status' => $status]], 202);
    }

    public function destroy(Request $request, string $projectId, string $derivationId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $updated = DB::table('project_document_derivations')->where('id', $derivationId)->where('project_id', $projectId)->update(['active' => false, 'updated_at' => now()]);
        abort_unless($updated === 1, 404);
        Log::info('document.derivation.deactivated', ['project_id' => $projectId, 'derivation_id' => $derivationId]);

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function present(object $row): array
    {
        $latest = DB::table('project_document_derivation_runs')->where('derivation_id', $row->id)->orderByDesc('created_at')->first();

        return [
            'id' => $row->id, 'sourceDocumentId' => $row->source_document_id, 'targetDocumentId' => $row->target_document_id,
            'processor' => $row->processor, 'processorVersion' => (int) $row->processor_version, 'definitionVersion' => (int) $row->definition_version,
            'configuration' => json_decode($row->configuration, true, flags: JSON_THROW_ON_ERROR),
            'autoRegenerate' => (bool) $row->auto_regenerate, 'active' => (bool) $row->active,
            'latestRun' => $latest ? ['id' => $latest->id, 'status' => $latest->status, 'attemptCount' => (int) $latest->attempt_count, 'errorCode' => $latest->error_code, 'errorMessage' => $latest->error_message] : null,
        ];
    }

    /** @param array{x:numeric,y:numeric,width:numeric,height:numeric} $rect */
    private function configuration(int $page, array $rect): array
    {
        $x = max(0.0, min(1.0, (float) $rect['x']));
        $y = max(0.0, min(1.0, (float) $rect['y']));
        $width = min((float) $rect['width'], 1.0 - $x);
        $height = min((float) $rect['height'], 1.0 - $y);
        abort_if($width <= 0 || $height <= 0, 422, 'A região precisa interceptar a página.');

        return [
            'page' => $page,
            'coordinateSpace' => 'effective-crop-box-top-left-v1',
            'rectNormalized' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
        ];
    }
}
