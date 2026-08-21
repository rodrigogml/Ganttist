<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TodoistSnapshotStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_date_update_moves_start_and_explicit_deadline_by_the_same_number_of_days(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $snapshotStore = app(TodoistSnapshotStore::class);
        $snapshotStore->put($projectId, ['tasks' => ['stale']]);

        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1', ['title' => 'Novo título', 'commandId' => 'invalid-date-update', 'start' => '2026-08-20'])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1/dates', ['intent' => 'MOVE', 'start' => '2026-08-20'])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1/dates', ['intent' => 'MOVE', 'start' => '2026-08-20', 'finish' => '2026-08-25', 'commandId' => 'invalid-finish'])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1/dates', ['intent' => 'MOVE', 'start' => '2026-08-20', 'commandId' => 'move-task'])
            ->assertOk()
            ->assertJsonPath('data.start', '2026-08-20')
            ->assertJsonPath('data.finish', '2026-08-22')
            ->assertJsonPath('data.deadline', '2026-08-22');
        self::assertNull($snapshotStore->get($projectId));
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'task.dates_updated', 'causation_id' => 'move-task']);
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-2/dates', ['intent' => 'MOVE', 'start' => '2026-08-25', 'commandId' => 'schedule-empty-task'])
            ->assertOk()
            ->assertJsonPath('data.start', '2026-08-25')
            ->assertJsonPath('data.finish', null)
            ->assertJsonPath('data.deadline', null);

        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-2/dates', ['intent' => 'RESIZE_START', 'start' => '2026-08-18', 'deadline' => '2026-08-20', 'commandId' => 'resize-start'])
            ->assertOk()
            ->assertJsonPath('data.start', '2026-08-18')
            ->assertJsonPath('data.deadline', '2026-08-20');
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-2/dates', ['intent' => 'RESIZE_END', 'deadline' => '2026-08-28', 'commandId' => 'resize-end'])
            ->assertOk()
            ->assertJsonPath('data.deadline', '2026-08-28');
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-2/dates', ['intent' => 'RESIZE_START', 'start' => '2026-08-21', 'deadline' => '2026-08-20', 'commandId' => 'invalid-range'])
            ->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1/dates', ['intent' => 'RESIZE_END', 'deadline' => '2026-08-28', 'commandId' => 'completed-resize'])
            ->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-group/dates', ['intent' => 'RESIZE_END', 'deadline' => '2026-08-28', 'commandId' => 'group-resize'])
            ->assertUnprocessable();
    }

    public function test_direct_task_update_requires_a_command_and_is_audited(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1', ['title' => 'Novo título', 'priority' => 4, 'completed' => true])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1', ['title' => 'Novo título', 'priority' => 4, 'completed' => true, 'commandId' => 'task-update'])->assertOk();

        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'task.updated', 'causation_id' => 'task-update']);
    }

    public function test_deletion_preview_requires_confirmation_and_can_preserve_route_continuity(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['fake-group', 'fake-task-1'], ['fake-task-1', 'fake-task-2']] as [$from, $to]) {
            DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => $from, 'successor_todoist_task_id' => $to, 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($user)->postJson('/api/v1/tasks/fake-task-1/deletion-preview')->assertOk()->assertJsonCount(1, 'data.incoming')->assertJsonCount(1, 'data.outgoing')->assertJsonPath('data.continuity.0.from', 'fake-group')->assertJsonPath('data.continuity.0.to', 'fake-task-2');
        $this->actingAs($user)->deleteJson('/api/v1/tasks/fake-task-1', ['preserveContinuity' => true, 'commandId' => 'delete-task'])->assertUnprocessable();
        $this->actingAs($user)->deleteJson('/api/v1/tasks/fake-task-1', ['confirmed' => true, 'preserveContinuity' => true, 'commandId' => 'delete-task'])->assertOk()->assertJsonPath('data.continuity_preserved', true);
        self::assertDatabaseHas('task_dependencies', ['gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-group', 'successor_todoist_task_id' => 'fake-task-2', 'status' => 'active']);
        self::assertDatabaseMissing('task_dependencies', ['gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'status' => 'active']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'task.deleted', 'causation_id' => 'delete-task']);
    }

    public function test_completed_task_can_store_an_effective_completion_override_without_writing_todoist(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->putJson('/api/v1/tasks/fake-task-1/completion-date', ['completionDate' => '2026-08-18', 'commandId' => 'completion-override'])->assertOk()->assertJsonPath('data.overridden', true);
        self::assertDatabaseHas('task_metadata', ['gantt_project_id' => $projectId, 'todoist_task_id' => 'fake-task-1', 'completion_date_override' => '2026-08-18']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'task.completion_date_overridden', 'causation_id' => 'completion-override']);
    }
}
