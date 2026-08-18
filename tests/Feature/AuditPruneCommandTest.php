<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_removes_only_events_outside_configured_retention(): void
    {
        config()->set('ganttist.audit_retention_days', 30);
        $this->event('old-event', now()->subDays(31));
        $this->event('current-event', now()->subDays(30)->addSecond());

        $this->artisan('audit:prune')->expectsOutput('Eventos de auditoria removidos: 1.')->assertSuccessful();

        self::assertDatabaseMissing('audit_events', ['id' => 'old-event']);
        self::assertDatabaseHas('audit_events', ['id' => 'current-event']);
    }

    private function event(string $id, \DateTimeInterface $occurredAt): void
    {
        DB::table('audit_events')->insert(['id' => $id, 'action' => 'test.event', 'origin' => 'test', 'occurred_at' => $occurredAt, 'created_at' => now(), 'updated_at' => now()]);
    }
}
