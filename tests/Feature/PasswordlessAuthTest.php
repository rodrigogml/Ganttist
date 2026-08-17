<?php

namespace Tests\Feature;

use App\Mail\MagicLoginLink;
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
            return $mail->hasTo('person@example.com') && str_contains($mail->url, '?token=');
        });

        $this->assertDatabaseHas('login_challenges', [
            'email' => 'person@example.com',
            'consumed_at' => null,
        ]);
    }

    public function test_one_time_challenge_creates_session_and_cannot_be_reused(): void
    {
        $token = str_repeat('a', 64);
        DB::table('login_challenges')->insert(['id' => (string) Str::ulid(), 'email' => 'person@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/auth/verify', ['token' => $token])->assertOk()->assertJsonPath('user.email', 'person@example.com');
        $this->assertAuthenticated();
        $this->postJson('/auth/verify', ['token' => $token])->assertStatus(422);
    }
}
