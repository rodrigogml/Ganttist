<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TodoistOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_ready_integration_skips_new_oauth_authorization(): void
    {
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('test-token'), 'status' => 'active', 'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_project_id' => 'project-1', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get('/oauth/todoist/redirect')
            ->assertRedirect('/?todoist=connected');

        $this->assertDatabaseCount('todoist_oauth_states', 0);
    }
}
