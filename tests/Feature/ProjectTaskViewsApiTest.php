<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProjectTaskViewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_are_provisioned_per_user_and_can_be_updated_or_deleted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'views'])->json('data.id');
        DB::table('project_members')->insert(['id' => (string) \Str::ulid(), 'project_id' => $project, 'user_id' => $member->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);

        $initial = $this->actingAs($owner)->getJson("/api/v1/projects/{$project}/views")->assertOk()->json('data');
        $this->assertSame(['Minhas Tarefas', 'Tarefas Equipe'], array_column($initial, 'name'));
        $this->actingAs($member)->getJson("/api/v1/projects/{$project}/views")->assertOk()->assertJsonCount(2, 'data');

        $created = $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/views", $this->payload('Futuras'))->assertCreated()->json('data');
        $this->assertArrayNotHasKey('ownerUserId', $created);
        $this->assertArrayNotHasKey('projectId', $created);
        $this->assertArrayNotHasKey('owner_user_id', $created);
        $this->assertArrayNotHasKey('project_id', $created);
        $this->actingAs($member)->putJson("/api/v1/projects/{$project}/views/{$created['id']}", $this->payload('Não permitida'))->assertNotFound();
        $this->actingAs($owner)->putJson("/api/v1/projects/{$project}/views/{$created['id']}", $this->payload('Futuras atualizada'))->assertOk()->assertJsonPath('data.name', 'Futuras atualizada');
        $this->actingAs($owner)->deleteJson("/api/v1/projects/{$project}/views/{$created['id']}")->assertNoContent();
    }

    public function test_view_names_are_private(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'history'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views", $this->payload('Aberto'))->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views", $this->payload('Aberto'))->assertConflict();
    }

    public function test_import_allows_a_copy_or_explicit_overwrite_without_resolving_textual_references(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Obra', 'commandId' => 'import'])->json('data.id');
        $original = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views", $this->payload('Aberto'))->json('data');
        $file = $this->payload('Aberto');
        $file['query'] = 'responsavel:"Pessoa ausente"';

        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views/import", ['file' => $file])->assertConflict();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views/import", ['file' => $file, 'conflictStrategy' => 'create'])->assertCreated()->assertJsonPath('data.name', 'Aberto (cópia)');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/views/import", ['file' => $file, 'conflictStrategy' => 'overwrite', 'targetViewId' => $original['id']])->assertOk()->assertJsonPath('data.query', 'responsavel:"Pessoa ausente"');
    }

    public function test_views_validate_the_contract_and_never_cross_project_or_user_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'A', 'commandId' => 'view-validation-a'])->json('data.id');
        $foreign = $this->actingAs($other)->postJson('/api/v1/projects', ['name' => 'B', 'commandId' => 'view-validation-b'])->json('data.id');
        $view = $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/views", $this->payload('Privada'))->assertCreated()->json('data');

        $this->actingAs($other)->getJson("/api/v1/projects/{$project}/views")->assertNotFound();
        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/views", [...$this->payload('Inválida'), 'query' => '(status:aberta'])->assertUnprocessable();
        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/views", [...$this->payload('Sem consulta'), 'query' => ''])->assertCreated()->assertJsonPath('data.query', '');
        $this->actingAs($other)->putJson("/api/v1/projects/{$foreign}/views/{$view['id']}", $this->payload('Tentativa'))->assertNotFound();
        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/views/import", ['file' => [...$this->payload('Arquivo inválido'), 'formatVersion' => 2]])->assertUnprocessable();
        $this->actingAs($owner)->getJson("/api/v1/projects/{$project}/views")->assertJsonPath('data.2.visualState.gantt.zoom', 'week');
    }

    private function payload(string $name): array
    {
        return ['name' => $name, 'query' => 'status:aberta', 'formatVersion' => 1, 'visualState' => ['version' => 1, 'gantt' => ['zoom' => 'week']]];
    }
}
