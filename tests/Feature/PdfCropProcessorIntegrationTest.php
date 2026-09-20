<?php

namespace Tests\Feature;

use App\Contracts\Documents\DocumentProcessorContract;
use App\Jobs\Documents\ProcessDocumentDerivation;
use App\Models\User;
use App\Services\Documents\DocumentRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PdfCropProcessorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_ONE = 'JVBERi0xLjMKJeLjz9MKMSAwIG9iago8PAovUHJvZHVjZXIgKHB5cGRmKQo+PgplbmRvYmoKMiAwIG9iago8PAovVHlwZSAvUGFnZXMKL0NvdW50IDEKL0tpZHMgWyA0IDAgUiBdCj4+CmVuZG9iagozIDAgb2JqCjw8Ci9UeXBlIC9DYXRhbG9nCi9QYWdlcyAyIDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovVHlwZSAvUGFnZQovUmVzb3VyY2VzIDw8Cj4+Ci9NZWRpYUJveCBbIDAuMCAwLjAgMjAwIDIwMCBdCi9QYXJlbnQgMiAwIFIKPj4KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMDU0IDAwMDAwIG4gCjAwMDAwMDAxMTMgMDAwMDAgbiAKMDAwMDAwMDE2MiAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDUKL1Jvb3QgMyAwIFIKL0luZm8gMSAwIFIKPj4Kc3RhcnR4cmVmCjI1NgolJUVPRgo=';

    private const PDF_TWO = 'JVBERi0xLjMKJeLjz9MKMSAwIG9iago8PAovUHJvZHVjZXIgKHB5cGRmKQo+PgplbmRvYmoKMiAwIG9iago8PAovVHlwZSAvUGFnZXMKL0NvdW50IDEKL0tpZHMgWyA0IDAgUiBdCj4+CmVuZG9iagozIDAgb2JqCjw8Ci9UeXBlIC9DYXRhbG9nCi9QYWdlcyAyIDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovVHlwZSAvUGFnZQovUmVzb3VyY2VzIDw8Cj4+Ci9NZWRpYUJveCBbIDAuMCAwLjAgMzAwIDIwMCBdCi9QYXJlbnQgMiAwIFIKPj4KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMDU0IDAwMDAwIG4gCjAwMDAwMDAxMTMgMDAwMDAgbiAKMDAwMDAwMDE2MiAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDUKL1Jvb3QgMyAwIFIKL0luZm8gMSAwIFIKPj4Kc3RhcnR4cmVmCjI1NgolJUVPRgo=';

    public function test_real_processor_keeps_vector_pdf_provenance_and_never_promotes_a_stale_run(): void
    {
        [$user, $project, $source] = $this->sourceDocument();
        $oldRevisionId = $source['currentRevision']['id'];

        $newRevision = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents/{$source['id']}/revisions", [
            'revisionLabel' => 'R02', 'file' => UploadedFile::fake()->createWithContent('source-r02.pdf', base64_decode(self::PDF_TWO, true)),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $created = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source['id']}/derivations", ['items' => [[
            'name' => 'Recorte', 'page' => 1, 'rectNormalized' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.5, 'height' => 0.5],
        ]]])->assertAccepted()->json('data.0');
        $derivation = DB::table('project_document_derivations')->where('id', $created['derivationId'])->firstOrFail();
        $oldRunId = app(DocumentRevisionService::class)->ensureRun($derivation, $oldRevisionId);
        $processor = app(DocumentProcessorContract::class);

        $processor->process($created['runId']);
        $currentAfterNewRun = DB::table('project_documents')->where('id', $created['documentId'])->value('current_revision_id');
        $processor->process($oldRunId);

        $this->assertSame($currentAfterNewRun, DB::table('project_documents')->where('id', $created['documentId'])->value('current_revision_id'));
        $this->assertDatabaseHas('project_document_derivation_runs', ['id' => $created['runId'], 'status' => 'succeeded']);
        $this->assertDatabaseHas('project_document_derivation_runs', ['id' => $oldRunId, 'status' => 'succeeded']);
        $revisions = DB::table('project_document_revisions')->where('document_id', $created['documentId'])->orderBy('revision_sequence')->get();
        $this->assertSame([1, 2], $revisions->pluck('revision_sequence')->map(fn ($value): int => (int) $value)->all());
        $this->assertSame('R02', $revisions[0]->revision_label);
        $this->assertSame('R01', $revisions[1]->revision_label);
        $this->assertSame($newRevision['id'], $revisions[0]->source_revision_id);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($revisions[0]->storage_key));
    }

    public function test_unsupported_processor_is_sanitized_and_not_retried(): void
    {
        [$user, $project, $source] = $this->sourceDocument();
        $created = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source['id']}/derivations", ['items' => [[
            'name' => 'Inválido', 'page' => 1, 'rectNormalized' => ['x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5],
        ]]])->assertAccepted()->json('data.0');
        DB::table('project_document_derivations')->where('id', $created['derivationId'])->update(['processor' => 'unknown.processor']);

        (new ProcessDocumentDerivation($created['runId']))->handle(app(DocumentProcessorContract::class));

        $this->assertDatabaseHas('project_document_derivation_runs', [
            'id' => $created['runId'], 'status' => 'failed', 'attempt_count' => 1,
            'error_code' => 'invalid_input', 'error_message' => 'O PDF ou a configuração da derivação é inválido.',
        ]);
    }

    /** @return array{User,string,array<string,mixed>} */
    private function sourceDocument(): array
    {
        Storage::fake('local');
        Queue::fake();
        config([
            'documents.disk' => 'local',
            'documents.processor.python' => PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3',
            'documents.processor.timeout_seconds' => 30,
        ]);
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'processor'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01',
            'file' => UploadedFile::fake()->createWithContent('source-r01.pdf', base64_decode(self::PDF_ONE, true)),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        return [$user, $project, $source];
    }
}
