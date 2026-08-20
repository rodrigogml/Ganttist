<?php

namespace Tests\Feature;

use App\Exceptions\TodoistReauthorizationRequired;
use App\Models\User;
use App\Services\TodoistAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
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

    public function test_active_integration_does_not_require_a_project_to_skip_authorization(): void
    {
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('test-token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get('/oauth/todoist/redirect')->assertRedirect('/?todoist=connected');
        $this->assertDatabaseCount('todoist_oauth_states', 0);
    }

    public function test_oauth_state_is_consumed_once_and_replaces_the_encrypted_token(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake([
            'https://api.todoist.com/oauth/access_token' => Http::response(['access_token' => 'rotated-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600]),
            'https://api.todoist.com/api/v1/sync' => Http::response(['user' => ['id' => 'todoist-user']]),
        ]);
        $user = User::factory()->create();
        $state = str_repeat('s', 64);
        $stateId = (string) Str::ulid();
        DB::table('todoist_oauth_states')->insert(['id' => $stateId, 'user_id' => $user->id, 'state_hash' => hash('sha256', $state), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('old-token'), 'status' => 'reauthorization_required', 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/oauth/todoist/callback?code=authorized-code&state='.$state)->assertRedirect('/?todoist=connected');
        self::assertNotNull(DB::table('todoist_oauth_states')->where('id', $stateId)->value('consumed_at'));
        $integration = DB::table('todoist_integrations')->where('user_id', $user->id)->first();
        self::assertSame('rotated-token', decrypt($integration->access_token_encrypted));
        self::assertSame('todoist-user', $integration->todoist_user_id);
        self::assertSame('active', $integration->status);
        self::assertNotNull($integration->token_rotated_at);
        self::assertSame('refresh-token', decrypt($integration->refresh_token_encrypted));
        self::assertNotNull($integration->access_token_expires_at);

        $this->get('/oauth/todoist/callback?code=authorized-code&state='.$state)->assertRedirect('/?todoist=authorization_expired');
        Http::assertSentCount(2);
    }

    public function test_failed_token_exchange_does_not_consume_the_oauth_state_or_return_an_error_page(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(['https://api.todoist.com/oauth/access_token' => Http::response(['error' => 'temporarily_unavailable'], 503)]);
        $user = User::factory()->create();
        $state = str_repeat('f', 64);
        $stateId = (string) Str::ulid();
        DB::table('todoist_oauth_states')->insert(['id' => $stateId, 'user_id' => $user->id, 'state_hash' => hash('sha256', $state), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/oauth/todoist/callback?code=authorized-code&state='.$state)
            ->assertRedirect('/?todoist=authorization_failed');

        self::assertNull(DB::table('todoist_oauth_states')->where('id', $stateId)->value('consumed_at'));
        $this->assertDatabaseMissing('todoist_integrations', ['user_id' => $user->id]);
    }

    public function test_cancelled_authorization_returns_to_the_application_without_419(): void
    {
        $this->get('/oauth/todoist/callback?error=access_denied&state=provider-state')
            ->assertRedirect('/?todoist=authorization_cancelled');
    }

    public function test_expired_access_token_is_refreshed_and_rotated_silently(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(['https://api.todoist.com/oauth/access_token' => Http::response(['access_token' => 'new-token', 'refresh_token' => 'new-refresh-token', 'expires_in' => 3600])]);
        $user = User::factory()->create();
        $id = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => $id, 'user_id' => $user->id, 'access_token_encrypted' => encrypt('expired-token'), 'refresh_token_encrypted' => encrypt('old-refresh-token'), 'access_token_expires_at' => now()->subMinute(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $token = app(TodoistAccessTokenService::class)->accessToken(DB::table('todoist_integrations')->where('id', $id)->first());

        self::assertSame('new-token', $token);
        $integration = DB::table('todoist_integrations')->where('id', $id)->first();
        self::assertSame('new-token', decrypt($integration->access_token_encrypted));
        self::assertSame('new-refresh-token', decrypt($integration->refresh_token_encrypted));
        Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'old-refresh-token');
    }

    public function test_refresh_reloads_the_integration_after_acquiring_the_lock(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $id = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => $id, 'user_id' => $user->id, 'access_token_encrypted' => encrypt('expired-token'), 'refresh_token_encrypted' => encrypt('old-refresh-token'), 'access_token_expires_at' => now()->subMinute(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $staleIntegration = DB::table('todoist_integrations')->where('id', $id)->first();
        DB::table('todoist_integrations')->where('id', $id)->update(['access_token_encrypted' => encrypt('already-rotated-token'), 'refresh_token_encrypted' => encrypt('already-rotated-refresh-token'), 'access_token_expires_at' => now()->addHour()]);

        self::assertSame('already-rotated-token', app(TodoistAccessTokenService::class)->accessToken($staleIntegration));
        Http::assertNothingSent();
    }

    public function test_transient_refresh_failure_does_not_require_reauthorization(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(['https://api.todoist.com/oauth/access_token' => Http::response(['error' => 'temporarily_unavailable'], 503)]);
        $user = User::factory()->create();
        $id = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => $id, 'user_id' => $user->id, 'access_token_encrypted' => encrypt('expired-token'), 'refresh_token_encrypted' => encrypt('refresh-token'), 'access_token_expires_at' => now()->subMinute(), 'status' => 'active', 'sync_state' => 'synced', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(RequestException::class);
        try {
            app(TodoistAccessTokenService::class)->accessToken(DB::table('todoist_integrations')->where('id', $id)->first());
        } finally {
            $this->assertDatabaseHas('todoist_integrations', ['id' => $id, 'status' => 'active', 'sync_state' => 'synced']);
        }
    }

    public function test_invalid_refresh_grant_requires_reauthorization(): void
    {
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(['https://api.todoist.com/oauth/access_token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $user = User::factory()->create();
        $id = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => $id, 'user_id' => $user->id, 'access_token_encrypted' => encrypt('expired-token'), 'refresh_token_encrypted' => encrypt('invalid-refresh-token'), 'access_token_expires_at' => now()->subMinute(), 'status' => 'active', 'sync_state' => 'synced', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(TodoistReauthorizationRequired::class);
        try {
            app(TodoistAccessTokenService::class)->accessToken(DB::table('todoist_integrations')->where('id', $id)->first());
        } finally {
            $this->assertDatabaseHas('todoist_integrations', ['id' => $id, 'status' => 'reauthorization_required', 'sync_state' => 'reauthorization_required']);
        }
    }
}
