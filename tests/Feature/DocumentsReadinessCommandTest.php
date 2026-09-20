<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DocumentsReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_readiness_validates_the_processor_queue_schema_and_private_storage(): void
    {
        Storage::fake('local');
        config([
            'documents.disk' => 'local',
            'documents.max_upload_kb' => 512000,
            'documents.processor.python' => PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3',
            'documents.processor.timeout_seconds' => 240,
            'queue.connections.documents.retry_after' => 360,
        ]);

        $this->artisan('documents:readiness', ['--write' => true])
            ->expectsOutput('Prontidão documental: aprovada.')
            ->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles('health/documents'));
    }

    public function test_document_readiness_rejects_an_unsafe_queue_window(): void
    {
        config([
            'documents.processor.python' => PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3',
            'queue.connections.documents.retry_after' => 300,
        ]);

        $this->artisan('documents:readiness')
            ->expectsOutput('retry_after da fila documental precisa superar o timeout do job.')
            ->expectsOutput('Prontidão documental: reprovada.')
            ->assertFailed();
    }
}
