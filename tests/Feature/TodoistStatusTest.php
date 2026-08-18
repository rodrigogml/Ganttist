<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TodoistStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_exposes_non_sensitive_pending_and_conflict_counts_for_the_active_project(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'sync_state' => 'degraded', 'last_sync_error' => 'rate_limited', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'p1', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['pending', 'conflict', 'applied'] as $state) {
            DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'command_id' => 'cmd-'.$state, 'operation' => 'recalculation.apply', 'state' => $state, 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($user)->getJson('/api/v1/todoist/status')->assertOk()->assertJsonPath('sync_state', 'degraded')->assertJsonPath('last_sync_error', 'rate_limited')->assertJsonPath('pending_operations', 1)->assertJsonPath('conflict_operations', 1);
    }
}
