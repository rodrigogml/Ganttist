<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ObservabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_readiness_and_metrics_report_non_sensitive_operational_state(): void
    {
        DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'command_id' => 'pending-command', 'operation' => 'recalculation.apply', 'state' => 'pending', 'payload' => '{}', 'created_at' => now()->subMinutes(16), 'updated_at' => now()]);
        DB::table('todoist_events')->insert(['id' => (string) Str::ulid(), 'external_event_id' => 'event-pending', 'event_type' => 'item:updated', 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/ready')->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('checks.database', 'ok');
        $this->get('/api/v1/metrics')->assertOk()->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')->assertSee('ganttist_sync_operations_pending 1')->assertSee('ganttist_sync_operations_oldest_pending_seconds 960')->assertSee('ganttist_todoist_events_unprocessed 1');
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
