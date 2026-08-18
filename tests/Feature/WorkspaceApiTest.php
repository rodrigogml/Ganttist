<?php

namespace Tests\Feature;

use App\Contracts\TodoistGateway;
use App\Models\User;
use App\Services\RecalculationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_contract_contains_hierarchy_and_dependencies(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/workspace')->assertOk()->assertJsonPath('data.project.source', 'Todoist')->assertJsonCount(12, 'data.tasks')->assertJsonCount(6, 'data.dependencies');
    }

    public function test_schedule_simulation_validates_and_returns_changes(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'fake-user', 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Teste', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'fake-task-2', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->postJson('/api/v1/schedule/simulate', ['commandId' => (string) Str::ulid(), 'intent' => ['taskId' => 'fake-task-2', 'start' => '2026-08-17']])
            ->assertOk()->assertJsonPath('data.changes.0.start', '2026-08-20');
    }

    public function test_real_workspace_derives_parent_group_from_todoist_snapshot(): void
    {
        config()->set('services.todoist.demo_mode', false);
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'fake-user', 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Teste', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calendar_exceptions')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'date' => '2026-08-17', 'type' => 'NON_WORKING', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_metadata')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'todoist_task_id' => 'fake-task-1', 'completion_date_override' => '2026-08-18', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-group', 'successor_todoist_task_id' => 'fake-task-2', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/workspace')->assertOk();
        $group = collect($response->json('data.tasks'))->firstWhere('id', 'fake-group');
        self::assertSame('group', $group['kind']);
        self::assertSame('2026-08-17', $group['start']);
        self::assertSame('2026-08-18', $group['finish']);
        self::assertTrue($group['derived']);
        self::assertTrue($group['contains_critical']);
        self::assertSame(2, $response->json('data.stats.critical'));
        self::assertTrue($response->json('data.dependencies.0.critical'));
        self::assertTrue($response->json('data.tasks.2.planned'));
        self::assertSame('synced', $response->json('data.tasks.2.sync_status'));
        self::assertTrue(collect($response->json('data.tasks'))->firstWhere('id', 'fake-task-1')['calendar_inconsistent']);
        self::assertSame('MANUAL', $response->json('data.calendar.rescheduling_mode'));
        self::assertSame(1, $response->json('meta.version'));
    }

    public function test_schedule_apply_persists_an_idempotent_operation_without_calling_todoist(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        $commandId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'fake-user', 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Teste', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'fake-task-2', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $payload = ['commandId' => $commandId, 'intent' => ['taskId' => 'fake-task-2', 'start' => '2026-08-17']];
        $this->actingAs($user)->postJson('/api/v1/schedule/apply', $payload)->assertStatus(202)->assertJsonPath('data.state', 'pending')->assertJsonPath('data.items', 1);
        self::assertDatabaseCount('recalculations', 1);
        self::assertDatabaseCount('recalculation_items', 1);
        self::assertDatabaseCount('sync_operations', 1);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'recalculation.created', 'causation_id' => $commandId]);
        $operationId = DB::table('recalculations')->value('id');
        $this->actingAs($user)->getJson('/api/v1/schedule/operations/'.$operationId)->assertOk()->assertJsonPath('data.state', 'pending')->assertJsonCount(1, 'data.items');
        self::assertSame('completed', app(RecalculationProcessor::class)->process($operationId));
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $operationId, 'state' => 'applied']);
        self::assertDatabaseHas('recalculations', ['id' => $operationId, 'state' => 'completed']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'recalculation.completed', 'causation_id' => $commandId]);
        $this->actingAs($user)->postJson('/api/v1/schedule/apply', $payload)->assertOk()->assertJsonPath('data.idempotent', true);
        self::assertDatabaseCount('recalculations', 1);
    }

    public function test_recalculation_processor_preserves_partial_failure_for_retry(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        $recalculationId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'remote-project', 'display_name' => 'Teste', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recalculations')->insert(['id' => $recalculationId, 'gantt_project_id' => $projectId, 'command_id' => 'cmd-retry', 'mode' => 'MANUAL', 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recalculation_items')->insert(['id' => (string) Str::ulid(), 'recalculation_id' => $recalculationId, 'sequence' => 0, 'todoist_task_id' => 'task-a', 'before_state' => '{}', 'after_state' => json_encode(['start' => '2026-08-17', 'finish' => '2026-08-17']), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recalculation_items')->insert(['id' => (string) Str::ulid(), 'recalculation_id' => $recalculationId, 'sequence' => 1, 'todoist_task_id' => 'task-b', 'before_state' => '{}', 'after_state' => json_encode(['start' => '2026-08-18', 'finish' => '2026-08-18']), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'command_id' => 'cmd-retry', 'operation' => 'recalculation.apply', 'state' => 'pending', 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $gateway = new class implements TodoistGateway
        {
            public int $calls = 0;

            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [['id' => 'task-a', 'due' => null, 'deadline_date' => null], ['id' => 'task-b', 'due' => null, 'deadline_date' => null]]]];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                if (++$this->calls === 1) {
                    throw new \RuntimeException('temporário');
                }

                return ['id' => $taskId];
            }

            public function updateTask(string $accessToken, string $taskId, array $attributes): array
            {
                return [];
            }

            public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

            public function createTask(string $accessToken, array $attributes): array
            {
                return [];
            }

            public function deleteTask(string $accessToken, string $taskId): void {}
        };
        app()->instance(TodoistGateway::class, $gateway);

        self::assertSame('retry', app(RecalculationProcessor::class)->process($recalculationId));
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $recalculationId, 'state' => 'pending_retry', 'attempts' => 1]);
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $recalculationId, 'todoist_task_id' => 'task-b', 'state' => 'pending', 'attempts' => 0]);
        self::assertDatabaseHas('recalculations', ['id' => $recalculationId, 'state' => 'partial']);
        self::assertDatabaseHas('sync_operations', ['command_id' => 'cmd-retry', 'state' => 'partial']);
        self::assertSame('completed', app(RecalculationProcessor::class)->process($recalculationId));
        self::assertSame(3, $gateway->calls);
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $recalculationId, 'state' => 'applied', 'attempts' => 2]);
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $recalculationId, 'todoist_task_id' => 'task-b', 'state' => 'applied', 'attempts' => 1]);
        self::assertDatabaseHas('recalculations', ['id' => $recalculationId, 'state' => 'completed']);
    }

    public function test_recalculation_detects_a_remote_change_as_a_conflict_without_overwriting_it(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        $recalculationId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'remote-project', 'display_name' => 'Teste', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recalculations')->insert(['id' => $recalculationId, 'gantt_project_id' => $projectId, 'command_id' => 'cmd-conflict', 'mode' => 'MANUAL', 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('recalculation_items')->insert(['id' => (string) Str::ulid(), 'recalculation_id' => $recalculationId, 'sequence' => 0, 'todoist_task_id' => 'task-a', 'before_state' => json_encode(['start' => '2026-08-17', 'finish' => '2026-08-17']), 'after_state' => json_encode(['start' => '2026-08-20', 'finish' => '2026-08-20']), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'command_id' => 'cmd-conflict', 'operation' => 'recalculation.apply', 'state' => 'pending', 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        app()->instance(TodoistGateway::class, new class implements TodoistGateway
        {
            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [['id' => 'task-a', 'due' => ['date' => '2026-08-18'], 'deadline_date' => '2026-08-18']]]];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                throw new \LogicException('A tarefa conflitada não deve ser escrita.');
            }

            public function updateTask(string $accessToken, string $taskId, array $attributes): array
            {
                return [];
            }

            public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

            public function createTask(string $accessToken, array $attributes): array
            {
                return [];
            }

            public function deleteTask(string $accessToken, string $taskId): void {}
        });

        self::assertSame('failed', app(RecalculationProcessor::class)->process($recalculationId));
        self::assertDatabaseHas('recalculation_items', ['recalculation_id' => $recalculationId, 'state' => 'stale', 'error' => 'snapshot_changed']);
        self::assertDatabaseHas('recalculations', ['id' => $recalculationId, 'state' => 'conflict']);
        self::assertDatabaseHas('sync_operations', ['command_id' => 'cmd-conflict', 'state' => 'conflict']);
    }
}
