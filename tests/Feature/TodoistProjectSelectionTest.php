<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TodoistProjectSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_project_archives_the_previous_active_workspace(): void
    {
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_project_id' => 'old-project', 'display_name' => 'Anterior', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        config()->set('services.todoist.driver', 'fake');
        $this->actingAs($user)->postJson('/api/v1/todoist/project', ['todoist_project_id' => 'fake-project', 'display_name' => 'Nome não confiável', 'commandId' => 'select-project'])->assertCreated();
        self::assertDatabaseHas('gantt_projects', ['user_id' => $user->id, 'todoist_project_id' => 'old-project', 'status' => 'archived']);
        self::assertDatabaseHas('gantt_projects', ['user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto de demonstração', 'status' => 'active']);
        self::assertDatabaseHas('audit_events', ['action' => 'todoist.project.selected', 'causation_id' => 'select-project']);
        self::assertSame(1, DB::table('gantt_projects')->where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_project_outside_the_connected_todoist_account_is_rejected(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->postJson('/api/v1/todoist/project', ['todoist_project_id' => 'other-project', 'commandId' => 'unknown-project'])->assertUnprocessable();
        self::assertDatabaseCount('gantt_projects', 0);
    }

    public function test_disconnect_requires_a_command_and_audits_the_revocation(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->deleteJson('/api/v1/todoist/integration')->assertUnprocessable();
        $this->actingAs($user)->deleteJson('/api/v1/todoist/integration', ['commandId' => 'disconnect-todoist'])->assertOk();

        self::assertDatabaseHas('todoist_integrations', ['user_id' => $user->id, 'status' => 'disconnected', 'access_token_encrypted' => null]);
        self::assertDatabaseHas('gantt_projects', ['id' => $projectId, 'status' => 'archived']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'todoist.integration.disconnected', 'causation_id' => 'disconnect-todoist']);
    }
}
