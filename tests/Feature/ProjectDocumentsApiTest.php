<?php

namespace Tests\Feature;

use App\Jobs\Documents\ProcessDocumentDerivation;
use App\Jobs\Documents\PurgeProjectDocumentFiles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProjectDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_tag_upload_document_and_add_immutable_revision(): void
    {
        Storage::fake('local');
        config(['documents.disk' => 'local']);
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'docs'])->json('data.id');
        DB::table('project_members')->insert([
            'id' => (string) \Str::ulid(), 'project_id' => $project, 'user_id' => $user->id, 'role' => 'editor',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tag = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Arquitetura'])->assertCreated()->json('data.id');

        $document = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Planta geral', 'revisionLabel' => 'R01', 'tagIds' => [$tag],
            'file' => UploadedFile::fake()->createWithContent('planta.pdf', "%PDF-1.4\nR01"),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->assertSame('application/pdf', $document['currentRevision']['mimeType']);
        $this->assertSame('R01', $document['currentRevision']['label']);
        $this->assertSame($tag, $document['tags'][0]['id']);

        $this->actingAs($user)->post("/api/v1/projects/{$project}/documents/{$document['id']}/revisions", [
            'revisionLabel' => 'R02', 'file' => UploadedFile::fake()->createWithContent('planta-r02.pdf', "%PDF-1.4\nR02"),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.sequence', 2);

        $this->assertDatabaseCount('project_document_revisions', 2);
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents/{$document['id']}/revisions")
            ->assertOk()->assertJsonPath('data.0.label', 'R02')->assertJsonPath('data.1.label', 'R01');
    }

    public function test_reader_cannot_mutate_and_cross_project_tags_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $first = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'A', 'commandId' => 'a'])->json('data.id');
        $second = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'B', 'commandId' => 'b'])->json('data.id');
        $foreignTag = $this->actingAs($owner)->postJson("/api/v1/projects/{$second}/tags", ['name' => 'Outra'])->json('data.id');
        \DB::table('project_members')->insert(['id' => (string) \Str::ulid(), 'project_id' => $first, 'user_id' => $reader->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($reader)->postJson("/api/v1/projects/{$first}/tags", ['name' => 'Negada'])->assertForbidden();
        $this->actingAs($owner)->post("/api/v1/projects/{$first}/documents", [
            'name' => 'Documento', 'revisionLabel' => 'R01', 'tagIds' => [$foreignTag],
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'conteudo'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('project_documents', 0);
    }

    public function test_content_endpoint_supports_authorized_byte_ranges(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'range'])->json('data.id');
        $document = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Texto', 'revisionLabel' => 'A', 'file' => UploadedFile::fake()->createWithContent('a.txt', '0123456789'),
        ], ['Accept' => 'application/json'])->json('data');

        $response = $this->actingAs($user)->withHeader('Range', 'bytes=2-5')->get($document['currentRevision']['contentUrl']);
        $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10');
        $this->assertSame('2345', $response->streamedContent());
    }

    public function test_pdf_derivation_batch_is_transactional_and_dispatched(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'derive'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nsource"),
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source}/derivations", ['items' => [[
            'name' => 'Térreo', 'page' => 1, 'rectNormalized' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.5, 'height' => 0.5],
        ]]])->assertStatus(202)->assertJsonPath('data.0.documentId', fn ($value) => is_string($value));

        $this->assertDatabaseCount('project_document_derivations', 1);
        $this->assertDatabaseHas('project_document_derivation_runs', ['status' => 'queued']);
        Queue::assertPushed(ProcessDocumentDerivation::class);
    }

    public function test_tag_hierarchy_rejects_cycles_and_archiving_releases_the_scoped_name(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'tags'])->json('data.id');
        $root = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Projetos'])->assertCreated()->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Executivo', 'parentTagId' => $root])->assertCreated()->json('data.id');

        $this->actingAs($user)->patchJson("/api/v1/projects/{$project}/tags/{$root}", ['parentTagId' => $child])->assertUnprocessable();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/tags/{$root}")->assertConflict();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/tags/{$child}")->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/tags/{$root}")->assertNoContent();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'projetos'])->assertCreated();
    }

    public function test_archiving_preserves_provenance_and_requires_the_source_to_be_restored_first(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'archive'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nsource"),
        ], ['Accept' => 'application/json'])->json('data.id');
        $derived = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source}/derivations", ['items' => [[
            'name' => 'Detalhe', 'page' => 1, 'rectNormalized' => ['x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5],
        ]]])->json('data.0.documentId');

        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/documents/{$source}")->assertConflict();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/documents/{$derived}")->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/documents/{$source}")->assertNoContent();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$derived}/restore")->assertConflict();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source}/restore")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$derived}/restore")->assertOk();
        $this->assertDatabaseCount('project_document_derivations', 1);
    }

    public function test_new_source_revision_schedules_one_idempotent_automatic_regeneration_and_manifest_is_complete(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'offline'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nsource-1"),
        ], ['Accept' => 'application/json'])->json('data');
        $derivation = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source['id']}/derivations", ['items' => [[
            'name' => 'Detalhe', 'page' => 1, 'rectNormalized' => ['x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5], 'autoRegenerate' => true,
        ]]])->json('data.0.derivationId');

        $currentRevision = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents/{$source['id']}/revisions", [
            'revisionLabel' => 'R02', 'file' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nsource-2"),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertDatabaseCount('project_document_derivation_runs', 2);

        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/derivations/{$derivation}/regenerate")->assertStatus(202);
        $this->assertDatabaseCount('project_document_derivation_runs', 2);
        Queue::assertPushed(ProcessDocumentDerivation::class, 3);

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/offline-manifest")
            ->assertOk()
            ->assertJsonPath('data.schemaVersion', 1)
            ->assertJsonPath('data.projectId', $project)
            ->assertJsonPath('data.files.0.sha256', $currentRevision['sha256'])
            ->assertJsonPath('data.totalBytes', $currentRevision['sizeBytes']);
    }

    public function test_s3_contract_uses_private_generated_keys_and_project_deletion_schedules_purge(): void
    {
        Storage::fake('s3', ['visibility' => 'private']);
        Queue::fake();
        config(['documents.disk' => 's3']);
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 's3'])->json('data.id');
        $document = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Contrato', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('contrato.txt', 'privado'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $revision = \DB::table('project_document_revisions')->where('id', $document['currentRevision']['id'])->firstOrFail();

        $this->assertSame('s3', $revision->storage_disk);
        $this->assertStringStartsWith("projects/{$project}/documents/{$document['id']}/revisions/", $revision->storage_key);
        $this->assertStringNotContainsString('contrato.txt', $revision->storage_key);
        Storage::disk('s3')->assertExists($revision->storage_key);
        $this->assertSame('private', config('filesystems.disks.s3.visibility'));

        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}")->assertNoContent();
        Queue::assertPushed(PurgeProjectDocumentFiles::class, fn (PurgeProjectDocumentFiles $job): bool => $job->projectId === $project && $job->storageDisks === ['s3']);
    }

    public function test_reader_can_download_safely_but_cross_project_revision_ids_are_hidden(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'safe-download'])->json('data.id');
        $otherProject = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Outra', 'commandId' => 'other-download'])->json('data.id');
        \DB::table('project_members')->insert(['id' => (string) \Str::ulid(), 'project_id' => $project, 'user_id' => $reader->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);
        $document = $this->actingAs($owner)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Página', 'revisionLabel' => 'A', 'file' => UploadedFile::fake()->createWithContent('page.html', '<h1>privado</h1>'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $contentUrl = $document['currentRevision']['contentUrl'];

        $response = $this->actingAs($reader)->get($contentUrl);
        $response->assertOk()
            ->assertHeader('ETag', '"'.hash('sha256', '<h1>privado</h1>').'"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="page.html"');
        $this->actingAs($reader)->call('HEAD', $contentUrl)->assertOk()->assertHeader('Accept-Ranges', 'bytes');
        $this->actingAs($reader)->withHeader('Range', 'bytes=999-1000')->get($contentUrl)
            ->assertStatus(416)->assertHeader('Content-Range', 'bytes */16');
        $foreignUrl = str_replace("/projects/{$project}/", "/projects/{$otherProject}/", $contentUrl);
        $this->actingAs($reader)->get($foreignUrl)->assertNotFound();
    }

    public function test_document_listing_intersects_tag_descendants_and_filters_kind_mime_and_state(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'filters'])->json('data.id');
        $root = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Projetos'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Executivo', 'parentTagId' => $root])->json('data.id');
        $phase = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tags", ['name' => 'Fase 1'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01', 'tagIds' => [$child, $phase],
            'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nsource"),
        ], ['Accept' => 'application/json'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source}/derivations", ['items' => [[
            'name' => 'Detalhe', 'page' => 1, 'rectNormalized' => ['x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5], 'tagIds' => [$child, $phase],
        ]]])->assertAccepted();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?tagIds[]={$root}&tagIds[]={$phase}&kind=manual&mimeFamily=application&perPage=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Prancha')->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?state=queued")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Detalhe');
    }

    public function test_duplicate_revision_is_rejected_and_derivation_definition_clamps_and_retries_idempotently(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'idempotent'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Prancha', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nsame"),
        ], ['Accept' => 'application/json'])->json('data');
        $this->actingAs($user)->post("/api/v1/projects/{$project}/documents/{$source['id']}/revisions", [
            'revisionLabel' => 'R02', 'file' => UploadedFile::fake()->createWithContent('same.pdf', "%PDF-1.4\nsame"),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->assertSame($source['currentRevision']['id'], \DB::table('project_documents')->where('id', $source['id'])->value('current_revision_id'));

        $derivation = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source['id']}/derivations", ['items' => [[
            'name' => 'Borda', 'page' => 1, 'rectNormalized' => ['x' => 0.8, 'y' => 0.9, 'width' => 0.5, 'height' => 0.5],
        ]]])->assertAccepted()->json('data.0');
        $stored = json_decode(\DB::table('project_document_derivations')->where('id', $derivation['derivationId'])->value('configuration'), true);
        $this->assertEqualsWithDelta(0.2, $stored['rectNormalized']['width'], 0.000001);
        $this->assertEqualsWithDelta(0.1, $stored['rectNormalized']['height'], 0.000001);

        \DB::table('project_document_derivation_runs')->where('id', $derivation['runId'])->update(['status' => 'failed', 'error_code' => 'processor_error']);
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/derivations/{$derivation['derivationId']}/regenerate")
            ->assertAccepted()->assertJsonPath('data.runId', $derivation['runId'])->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseCount('project_document_derivation_runs', 1);
    }

    public function test_ready_queued_failed_and_outdated_filters_follow_the_latest_derivation_state(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'states'])->json('data.id');
        $source = $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Origem', 'revisionLabel' => 'R01', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nfirst"),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $created = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/documents/{$source['id']}/derivations", ['items' => [[
            'name' => 'Derivado', 'page' => 1, 'rectNormalized' => ['x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5],
        ]]])->assertAccepted()->json('data.0');

        DB::table('project_document_derivation_runs')->where('id', $created['runId'])->update(['status' => 'failed']);
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?state=failed")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Derivado');

        $derivedRevisionId = (string) \Str::ulid();
        DB::table('project_document_revisions')->insert([
            'id' => $derivedRevisionId, 'document_id' => $created['documentId'], 'revision_sequence' => 1, 'revision_label' => 'R01',
            'mime_type' => 'application/pdf', 'storage_disk' => 'local', 'storage_key' => 'derived/content', 'size_bytes' => 10,
            'sha256' => str_repeat('a', 64), 'source_revision_id' => $source['currentRevision']['id'], 'derivation_id' => $created['derivationId'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_documents')->where('id', $created['documentId'])->update(['current_revision_id' => $derivedRevisionId]);
        DB::table('project_document_derivation_runs')->where('id', $created['runId'])->update(['status' => 'succeeded', 'target_revision_id' => $derivedRevisionId]);
        $this->actingAs($user)->post("/api/v1/projects/{$project}/documents/{$source['id']}/revisions", [
            'revisionLabel' => 'R02', 'file' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nsecond"),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?state=queued")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Derivado');
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?state=outdated")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Derivado');
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/documents?state=ready")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Origem');
    }

    public function test_account_deletion_schedules_document_prefix_purge(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'account-purge'])->json('data.id');
        $this->actingAs($user)->post("/api/v1/projects/{$project}/documents", [
            'name' => 'Arquivo', 'revisionLabel' => 'A', 'file' => UploadedFile::fake()->createWithContent('a.txt', 'private'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($user)->deleteJson('/auth/account')->assertOk();

        Queue::assertPushed(PurgeProjectDocumentFiles::class, fn (PurgeProjectDocumentFiles $job): bool => $job->projectId === $project && $job->storageDisks === ['local']);
    }

    public function test_purge_job_removes_the_project_prefix_from_every_used_disk(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        $projectId = (string) \Str::ulid();
        $local = "projects/{$projectId}/documents/a/revisions/1/content";
        $s3 = "projects/{$projectId}/documents/b/revisions/2/content";
        Storage::disk('local')->put($local, 'local');
        Storage::disk('s3')->put($s3, 's3');

        (new PurgeProjectDocumentFiles($projectId, ['local', 's3', 'local']))->handle();

        Storage::disk('local')->assertMissing($local);
        Storage::disk('s3')->assertMissing($s3);
    }
}
