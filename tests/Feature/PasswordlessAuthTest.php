<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PasswordlessAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_challenge_creates_session_and_cannot_be_reused(): void
    {
        $token = str_repeat('a', 64);
        DB::table('login_challenges')->insert(['id' => (string) Str::ulid(), 'email' => 'person@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/auth/verify', ['token' => $token])->assertOk()->assertJsonPath('user.email', 'person@example.com');
        $this->assertAuthenticated();
        $this->postJson('/auth/verify', ['token' => $token])->assertStatus(422);
    }
}
