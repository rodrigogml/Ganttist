<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ObservabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_readiness_and_metrics_report_non_sensitive_operational_state(): void
    {
        DB::table('projects')->insert(['id' => (string) \Str::ulid(), 'owner_user_id' => User::factory()->create()->id, 'name' => 'Produto', 'creation_command_id' => 'metrics', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/ready')->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('checks.database', 'ok');
        $this->get('/api/v1/metrics')->assertOk()->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')->assertSee('ganttist_projects_total 1')->assertSee('ganttist_tasks_total 0')->assertSee('ganttist_invitations_pending 0');
    }

    public function test_every_response_carries_a_safe_request_correlation_id(): void
    {
        $this->withHeader('X-Request-ID', 'release-check-001')->getJson('/api/v1/health')->assertOk()->assertHeader('X-Request-ID', 'release-check-001');
        $this->withHeader('X-Request-ID', '<invalid>')->getJson('/api/v1/health')->assertOk()->assertHeader('X-Request-ID');
    }

    public function test_every_response_carries_baseline_security_headers(): void
    {
        $this->getJson('/api/v1/health')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    }
}
