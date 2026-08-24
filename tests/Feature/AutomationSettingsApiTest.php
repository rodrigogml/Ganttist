<?php

namespace Tests\Feature;

use App\Jobs\ApplyProjectAutomations;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AutomationSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_blocked_task_automation_with_an_independent_version(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'project-automation', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/settings/automation')
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.autoScheduleBlockedTasks', false)
            ->assertJsonPath('data.clearParentTaskDates', false);

        $payload = ['commandId' => 'automation-command', 'expectedVersion' => 1, 'autoScheduleBlockedTasks' => true, 'clearParentTaskDates' => true];
        $this->actingAs($user)->putJson('/api/v1/settings/automation', $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.autoScheduleBlockedTasks', true)
            ->assertJsonPath('data.clearParentTaskDates', true);

        self::assertDatabaseHas('project_settings', ['gantt_project_id' => $projectId, 'autoScheduleBlockedTasks' => true, 'clearParentTaskDates' => true, 'automationVersion' => 2]);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'automation.settings.updated', 'causation_id' => 'automation-command']);
        Queue::assertPushed(ApplyProjectAutomations::class, fn (ApplyProjectAutomations $job): bool => $job->projectId === $projectId);
        $this->actingAs($user)->putJson('/api/v1/settings/automation', $payload)->assertStatus(409);
    }

    public function test_deployed_legacy_columns_still_load_and_save_the_existing_automation(): void
    {
        Schema::table('project_settings', function (Blueprint $table): void {
            $table->dropColumn('clearParentTaskDates');
        });
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'legacy-automation', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/settings/automation')
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.autoScheduleBlockedTasks', false)
            ->assertJsonPath('data.clearParentTaskDates', false);

        $this->actingAs($user)->putJson('/api/v1/settings/automation', [
            'commandId' => 'legacy-command',
            'expectedVersion' => 1,
            'autoScheduleBlockedTasks' => true,
            'clearParentTaskDates' => false,
        ])->assertOk()->assertJsonPath('data.autoScheduleBlockedTasks', true);
    }
}
