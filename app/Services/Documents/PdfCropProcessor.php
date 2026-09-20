<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentProcessorContract;
use App\Exceptions\PermanentDocumentProcessingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class PdfCropProcessor implements DocumentProcessorContract
{
    public function process(string $runId): void
    {
        $startedAt = hrtime(true);
        $context = DB::table('project_document_derivation_runs as runs')
            ->join('project_document_derivations as derivations', 'derivations.id', '=', 'runs.derivation_id')
            ->join('project_document_revisions as source', 'source.id', '=', 'runs.source_revision_id')
            ->join('project_documents as target', 'target.id', '=', 'derivations.target_document_id')
            ->where('runs.id', $runId)
            ->first([
                'runs.*', 'derivations.project_id', 'derivations.target_document_id', 'derivations.configuration',
                'derivations.processor', 'derivations.processor_version', 'source.document_id as source_document_id', 'source.storage_disk as source_disk',
                'source.storage_key as source_key', 'source.revision_label as source_label', 'target.archived_at as target_archived_at',
            ]) ?? throw new PermanentDocumentProcessingException('Execução de derivação inexistente.');

        if ($context->status === 'succeeded') {
            return;
        }
        if ($context->processor !== 'pdf.crop.region' || (int) $context->processor_version !== 1) {
            $this->rejectRun($runId, 'Processador ou versão não suportado.');
        }
        if ($context->target_archived_at !== null) {
            $this->rejectRun($runId, 'Documento de destino arquivado.');
        }

        DB::table('project_document_derivation_runs')->where('id', $runId)->update([
            'status' => 'processing', 'attempt_count' => DB::raw('attempt_count + 1'), 'error_code' => null,
            'error_message' => null, 'started_at' => now(), 'finished_at' => null, 'updated_at' => now(),
        ]);
        Log::info('document.derivation.processing', $this->logContext($context, $runId, [
            'attempt' => ((int) $context->attempt_count) + 1,
        ]));

        $temporary = storage_path('app/tmp/documents/'.$runId.'-'.Str::random(8));
        $input = $temporary.DIRECTORY_SEPARATOR.'input.pdf';
        $output = $temporary.DIRECTORY_SEPARATOR.'output.pdf';
        $requestFile = $temporary.DIRECTORY_SEPARATOR.'request.json';

        try {
            if (! mkdir($temporary, 0700, true) && ! is_dir($temporary)) {
                throw new RuntimeException('Não foi possível criar diretório temporário.');
            }
            $source = Storage::disk($context->source_disk)->readStream($context->source_key);
            if (! is_resource($source)) {
                throw new PermanentDocumentProcessingException('Arquivo de origem ausente.');
            }
            $destination = fopen($input, 'wb');
            if ($destination === false) {
                throw new RuntimeException('Não foi possível criar arquivo temporário.');
            }
            try {
                stream_copy_to_stream($source, $destination);
            } finally {
                fclose($source);
                fclose($destination);
            }

            try {
                $configuration = json_decode($context->configuration, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new PermanentDocumentProcessingException('Configuração JSON inválida.', previous: $exception);
            }
            $payload = [
                'schema_version' => 1, 'source_path' => $input, 'output_path' => $output,
                'configuration' => $configuration,
            ];
            file_put_contents($requestFile, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $process = new Process([(string) config('documents.processor.python'), (string) config('documents.processor.script'), $requestFile]);
            $process->setTimeout((float) config('documents.processor.timeout_seconds', 240));
            $process->run();
            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Falha no processador PDF.';
                if (str_contains($message, 'INVALID_')) {
                    throw new PermanentDocumentProcessingException(mb_substr($message, 0, 1000));
                }
                throw new RuntimeException(mb_substr($message, 0, 1000));
            }
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($result) || ($result['schema_version'] ?? null) !== 1) {
                throw new RuntimeException('O processador retornou uma resposta incompatível.');
            }
            if (! is_file($output) || filesize($output) < 5 || file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
                throw new PermanentDocumentProcessingException('O processador não gerou um PDF válido.');
            }
            $this->commitResult($context, $runId, $output, $result);
            Log::info('document.derivation.succeeded', $this->logContext($context, $runId, [
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]));
        } catch (Throwable $exception) {
            $code = $exception instanceof PermanentDocumentProcessingException ? 'invalid_input' : 'processor_error';
            $publicMessage = $exception instanceof PermanentDocumentProcessingException
                ? 'O PDF ou a configuração da derivação é inválido.'
                : 'O processador de documentos falhou. Tente novamente.';
            DB::table('project_document_derivation_runs')->where('id', $runId)->update([
                'status' => 'failed', 'error_code' => $code, 'error_message' => $publicMessage,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            Log::warning('document.derivation.failed', $this->logContext($context, $runId, [
                'error_code' => $code, 'exception' => $exception::class,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]));
            throw $exception;
        } finally {
            foreach ([$requestFile, $input, $output] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            if (is_dir($temporary)) {
                @rmdir($temporary);
            }
        }
    }

    /** @param array<string, mixed> $result */
    private function commitResult(object $context, string $runId, string $output, array $result): void
    {
        $revisionId = (string) Str::ulid();
        $disk = (string) config('documents.disk', 'local');
        $key = "projects/{$context->project_id}/documents/{$context->target_document_id}/revisions/{$revisionId}/content";
        $stream = fopen($output, 'rb');
        if ($stream === false || ! Storage::disk($disk)->writeStream($key, $stream, ['visibility' => 'private'])) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('Não foi possível armazenar o PDF derivado.');
        }
        fclose($stream);

        try {
            $committed = DB::transaction(function () use ($context, $runId, $revisionId, $disk, $key, $output): bool {
                $run = DB::table('project_document_derivation_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
                if ($run->status === 'succeeded') {
                    return false;
                }
                $derivation = DB::table('project_document_derivations')->where('id', $context->derivation_id)->lockForUpdate()->firstOrFail();
                $sourceDocument = DB::table('project_documents')->where('id', $context->source_document_id)->lockForUpdate()->firstOrFail();
                $targetDocument = DB::table('project_documents')->where('id', $context->target_document_id)->lockForUpdate()->firstOrFail();
                $sequence = ((int) DB::table('project_document_revisions')->where('document_id', $context->target_document_id)->max('revision_sequence')) + 1;
                DB::table('project_document_revisions')->insert([
                    'id' => $revisionId, 'document_id' => $context->target_document_id, 'revision_sequence' => $sequence,
                    'revision_label' => $context->source_label, 'mime_type' => 'application/pdf', 'original_filename' => null,
                    'storage_disk' => $disk, 'storage_key' => $key, 'size_bytes' => filesize($output), 'sha256' => hash_file('sha256', $output),
                    'source_revision_id' => $context->source_revision_id, 'derivation_id' => $context->derivation_id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $promote = $targetDocument->current_revision_id === null
                    || ($sourceDocument->current_revision_id === $run->source_revision_id && (int) $derivation->definition_version === (int) $run->definition_version);
                if ($promote) {
                    DB::table('project_documents')->where('id', $context->target_document_id)->update(['current_revision_id' => $revisionId, 'updated_at' => now()]);
                }
                DB::table('project_document_derivation_runs')->where('id', $runId)->update([
                    'target_revision_id' => $revisionId, 'status' => 'succeeded', 'finished_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('projects')->where('id', $context->project_id)->update(['updated_at' => now()]);

                return true;
            });
            if (! $committed) {
                Storage::disk($disk)->delete($key);
            }
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($key);
            throw $exception;
        }
    }

    private function rejectRun(string $runId, string $reason): never
    {
        DB::table('project_document_derivation_runs')->where('id', $runId)->update([
            'status' => 'failed', 'attempt_count' => DB::raw('attempt_count + 1'),
            'error_code' => 'invalid_input', 'error_message' => 'O PDF ou a configuração da derivação é inválido.',
            'started_at' => now(), 'finished_at' => now(), 'updated_at' => now(),
        ]);
        Log::warning('document.derivation.failed', ['run_id' => $runId, 'error_code' => 'invalid_input']);

        throw new PermanentDocumentProcessingException($reason);
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function logContext(object $context, string $runId, array $extra = []): array
    {
        return $extra + [
            'run_id' => $runId,
            'derivation_id' => $context->derivation_id,
            'source_revision_id' => $context->source_revision_id,
            'target_document_id' => $context->target_document_id,
            'processor' => $context->processor,
            'processor_version' => (int) $context->processor_version,
        ];
    }
}
