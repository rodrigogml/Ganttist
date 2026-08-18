<?php

namespace Tests\Feature;

use App\Mail\MagicLoginLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PasswordlessAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_link_sends_a_single_use_email(): void
    {
        Mail::fake();

        $this->postJson('/auth/request-link', ['email' => 'Person@Example.com'])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Se o e-mail puder ser utilizado, enviaremos um link de acesso.');

        Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $mail): bool {
            return $mail->hasTo('person@example.com') && str_contains($mail->url, '?token=') && strlen($mail->pin) === 6;
        });

        $this->assertDatabaseHas('login_challenges', [
            'email' => 'person@example.com',
            'consumed_at' => null,
        ]);
    }

    public function test_pin_can_create_session(): void
    {
        Mail::fake();
        $this->postJson('/auth/request-link', ['email' => 'person@example.com'])->assertStatus(202);
        $mail = null;
        Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $sent) use (&$mail): bool {
            $mail = $sent;

            return true;
        });
        $this->postJson('/auth/verify', ['email' => 'person@example.com', 'pin' => $mail->pin])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_remember_option_creates_a_recaller_cookie(): void
    {
        Mail::fake();
        $this->postJson('/auth/request-link', ['email' => 'person@example.com', 'remember' => true])->assertStatus(202);
        $mail = null;
        Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $sent) use (&$mail): bool {
            $mail = $sent;

            return true;
        });
        $response = $this->postJson('/auth/verify', ['email' => 'person@example.com', 'pin' => $mail->pin])->assertOk();
        $this->assertTrue(collect($response->headers->getCookies())->contains(fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_web_')));
    }

    public function test_one_time_challenge_creates_session_and_cannot_be_reused(): void
    {
        $token = str_repeat('a', 64);
        DB::table('login_challenges')->insert(['id' => (string) Str::ulid(), 'email' => 'person@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/auth/verify', ['token' => $token])->assertOk()->assertJsonPath('user.email', 'person@example.com');
        $this->assertAuthenticated();
        $this->postJson('/auth/verify', ['token' => $token])->assertStatus(422);
    }

    public function test_expired_challenge_is_rejected_and_request_messages_do_not_enumerate_accounts(): void
    {
        $token = str_repeat('e', 64);
        DB::table('login_challenges')->insert(['id' => (string) Str::ulid(), 'email' => 'person@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/auth/verify', ['token' => $token])->assertStatus(422);

        Mail::fake();
        User::factory()->create(['email' => 'existing@example.com']);
        $existing = $this->postJson('/auth/request-link', ['email' => 'existing@example.com']);
        $unknown = $this->postJson('/auth/request-link', ['email' => 'unknown@example.com']);
        $existing->assertStatus(202);
        $unknown->assertStatus(202);
        self::assertSame($existing->json('message'), $unknown->json('message'));
    }

    public function test_session_listing_and_revocation_are_isolated_to_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'owner-session', 'user_id' => $owner->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Owner browser', 'payload' => 'x', 'last_activity' => now()->timestamp]);
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $other->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Other browser', 'payload' => 'x', 'last_activity' => now()->timestamp]);

        $this->actingAs($owner)->getJson('/api/v1/sessions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'owner-session');
        $this->actingAs($owner)->deleteJson('/api/v1/sessions/other-session')->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'other-session', 'user_id' => $other->id]);
    }

    public function test_authenticated_user_can_delete_own_account_and_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'delete-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'payload' => 'x', 'last_activity' => now()->timestamp]);

        $this->actingAs($user)->deleteJson('/auth/account')->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'delete-session']);
    }
}
