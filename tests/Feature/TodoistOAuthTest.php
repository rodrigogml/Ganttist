<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_revoked_integration_is_reported_as_reauthorization_required(): void
    {
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('old-token'), 'status' => 'reauthorization_required', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/todoist/status')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('integration_status', 'reauthorization_required');
    }

    public function test_oauth_state_is_consumed_once_and_replaces_the_encrypted_token(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(['https://todoist.com/oauth/access_token' => Http::response(['access_token' => 'rotated-token', 'user_id' => 'todoist-user'])]);
        $user = User::factory()->create();
        $state = str_repeat('s', 64);
        $stateId = (string) Str::ulid();
        DB::table('todoist_oauth_states')->insert(['id' => $stateId, 'user_id' => $user->id, 'state_hash' => hash('sha256', $state), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('old-token'), 'status' => 'reauthorization_required', 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/oauth/todoist/callback?code=authorized-code&state='.$state)->assertRedirect('/?todoist=connected');
        self::assertNotNull(DB::table('todoist_oauth_states')->where('id', $stateId)->value('consumed_at'));
        $integration = DB::table('todoist_integrations')->where('user_id', $user->id)->first();
        self::assertSame('rotated-token', decrypt($integration->access_token_encrypted));
        self::assertSame('active', $integration->status);
        self::assertNotNull($integration->token_rotated_at);

        $this->get('/oauth/todoist/callback?code=authorized-code&state='.$state)->assertStatus(419);
        Http::assertSentCount(1);
    }
}
