<?php

namespace Tests\Feature;

use App\Contracts\TodoistGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_version_calendar_and_replace_exceptions(): void
    {
        [$user, $projectId] = $this->projectWithSettings();
        $payload = ['commandId' => 'calendar-command', 'expectedVersion' => 1, 'workingDays' => ['monday', 'tuesday', 'wednesday', 'thursday'], 'reschedulingMode' => 'MANUAL', 'deadlinePolicy' => 'POSTERIOR', 'allowUnscheduledTasks' => true, 'exceptions' => [['date' => '2026-08-20', 'type' => 'NON_WORKING', 'description' => 'Feriado']]];

        $this->actingAs($user)->putJson('/api/v1/calendar', $payload)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.workingDays.3', 'thursday')->assertJsonPath('data.exceptions.0.type', 'NON_WORKING');
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'calendar.updated', 'causation_id' => 'calendar-command']);
        $this->actingAs($user)->putJson('/api/v1/calendar', $payload)->assertStatus(409);
    }

    public function test_calendar_requires_an_owner_project_and_at_least_one_working_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/calendar')->assertStatus(409);
        [$owner] = $this->projectWithSettings();
        $this->actingAs($owner)->putJson('/api/v1/calendar', ['commandId' => 'invalid-calendar', 'expectedVersion' => 1, 'workingDays' => [], 'reschedulingMode' => 'MANUAL', 'deadlinePolicy' => 'ANTERIOR', 'allowUnscheduledTasks' => true, 'exceptions' => []])->assertUnprocessable();
    }

    public function test_manual_calendar_impact_requires_simulation_confirmation_and_creates_an_operation(): void
    {
        [$user, $projectId] = $this->projectWithSettings();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->app->bind(TodoistGateway::class, fn () => new class implements TodoistGateway
        {
            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [['id' => 'task-1', 'content' => 'Entrega', 'is_completed' => false, 'due' => ['date' => '2026-08-17'], 'deadline_date' => '2026-08-19']]]];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                return [];
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
        $payload = ['commandId' => 'calendar-impact', 'expectedVersion' => 1, 'workingDays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'reschedulingMode' => 'MANUAL', 'deadlinePolicy' => 'ANTERIOR', 'allowUnscheduledTasks' => true, 'exceptions' => [['date' => '2026-08-17', 'type' => 'NON_WORKING', 'description' => null]]];
        $this->actingAs($user)->postJson('/api/v1/calendar/simulate', $payload)->assertOk()->assertJsonCount(1, 'data.changes')->assertJsonPath('data.changes.0.after.start', '2026-08-18');
        $this->actingAs($user)->putJson('/api/v1/calendar', $payload)->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/v1/calendar', $payload + ['confirmed' => true])->assertStatus(202)->assertJsonPath('operation_id', fn ($id) => is_string($id));
        self::assertDatabaseHas('recalculations', ['gantt_project_id' => $projectId, 'command_id' => 'calendar-impact', 'state' => 'pending']);
    }

    /** @return array{User, string} */
    private function projectWithSettings(): array
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'project-'.$projectId, 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $projectId];
    }
}
