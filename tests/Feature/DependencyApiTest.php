<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DependencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_dependency_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'p', 'display_name' => 'P', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'A', 'successor_todoist_task_id' => 'B', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->postJson('/api/v1/dependencies', ['from' => 'A', 'to' => 'B', 'type' => 'FS', 'commandId' => 'duplicate'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Essa dependência já existe.');
    }

    public function test_dependency_requires_tasks_from_the_active_todoist_project(): void
    {
        $user = $this->userWithActiveFakeProject();

        $this->actingAs($user)->postJson('/api/v1/dependencies', ['from' => 'fake-task-1', 'to' => 'outside-task', 'type' => 'FS', 'commandId' => 'outside'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'As tarefas da dependência devem pertencer ao projeto Todoist selecionado.');
    }

    public function test_group_is_allowed_only_as_predecessor_of_a_common_task(): void
    {
        $user = $this->userWithActiveFakeProject();

        $created = $this->actingAs($user)->postJson('/api/v1/dependencies', ['from' => 'fake-group', 'to' => 'fake-task-2', 'type' => 'FS', 'commandId' => 'group-created'])
            ->assertCreated()
            ->assertJsonPath('data.from', 'fake-group');
        self::assertDatabaseHas('audit_events', ['action' => 'dependency.created', 'causation_id' => 'group-created']);
        $this->actingAs($user)->deleteJson('/api/v1/dependencies/'.$created->json('data.id'), ['commandId' => 'group-deleted'])->assertOk();
        self::assertDatabaseHas('audit_events', ['action' => 'dependency.deleted', 'causation_id' => 'group-deleted']);
        $this->actingAs($user)->postJson('/api/v1/dependencies', ['from' => 'fake-task-2', 'to' => 'fake-group', 'type' => 'FS', 'commandId' => 'group-invalid'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Um grupo pode ser usado somente como predecessor de uma tarefa comum.');
    }

    public function test_cycle_is_rejected_before_persisting_the_dependency(): void
    {
        $user = $this->userWithActiveFakeProject();
        $projectId = DB::table('gantt_projects')->where('user_id', $user->id)->value('id');
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'fake-task-2', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->postJson('/api/v1/dependencies', ['from' => 'fake-task-2', 'to' => 'fake-task-1', 'type' => 'FS', 'commandId' => 'cycle'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Essa dependência criaria um ciclo no grafo.');
    }

    public function test_removed_dependency_is_reactivated_instead_of_violating_the_unique_key(): void
    {
        $user = $this->userWithActiveFakeProject();
        $payload = ['from' => 'fake-task-1', 'to' => 'fake-task-2', 'type' => 'FS'];

        $created = $this->actingAs($user)->postJson('/api/v1/dependencies', [...$payload, 'commandId' => 'dependency-first-create'])
            ->assertCreated();
        $dependencyId = $created->json('data.id');

        $this->actingAs($user)->deleteJson('/api/v1/dependencies/'.$dependencyId, ['commandId' => 'dependency-remove'])
            ->assertOk();

        $this->actingAs($user)->postJson('/api/v1/dependencies', [...$payload, 'commandId' => 'dependency-recreate'])
            ->assertCreated()
            ->assertJsonPath('data.id', $dependencyId);

        self::assertSame(1, DB::table('task_dependencies')->where('gantt_project_id', DB::table('gantt_projects')->where('user_id', $user->id)->value('id'))->where('predecessor_todoist_task_id', $payload['from'])->where('successor_todoist_task_id', $payload['to'])->where('type', $payload['type'])->count());
        self::assertDatabaseHas('task_dependencies', ['id' => $dependencyId, 'status' => 'active']);
        self::assertDatabaseHas('audit_events', ['action' => 'dependency.created', 'causation_id' => 'dependency-recreate']);
    }

    private function userWithActiveFakeProject(): User
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'fake-user', 'access_token_encrypted' => encrypt('fake-token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto fake', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
