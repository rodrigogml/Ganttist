<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\EventStreamController;
use App\Models\User;
use App\Services\WorkspaceEventFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkspaceEventFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_is_ordered_authorized_and_preserves_causation_for_echo_deduplication(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->event('01J00000000000000000000001', $owner->id, 'recalculation.created', 'cmd-1');
        $this->event('01J00000000000000000000002', $other->id, 'todoist.event.reconciled', 'evt-other');
        $this->event('01J00000000000000000000003', $owner->id, 'recalculation.completed', 'cmd-1');

        $events = app(WorkspaceEventFeed::class)->after($owner->id, '01J00000000000000000000000');

        self::assertSame(['01J00000000000000000000001', '01J00000000000000000000003'], $events->pluck('id')->all());
        self::assertSame(['cmd-1', 'cmd-1'], $events->pluck('causation_id')->all());
        self::assertSame(['eventId' => '01J00000000000000000000003', 'projectId' => null, 'action' => 'recalculation.completed', 'causationId' => 'cmd-1', 'occurredAt' => $events[1]->occurred_at], app(WorkspaceEventFeed::class)->payload($events[1]));
    }

    public function test_event_stream_uses_last_event_id_when_a_client_reconnects(): void
    {
        $user = User::factory()->create();
        $this->event('01J00000000000000000000009', $user->id, 'recalculation.completed', 'cmd-9');
        $controller = app(EventStreamController::class);
        $method = new \ReflectionMethod($controller, 'cursor');
        $request = Request::create('/api/v1/events');
        $request->headers->set('Last-Event-ID', '01J00000000000000000000008');
        self::assertSame('01J00000000000000000000008', $method->invoke($controller, $request, $user->id));
    }

    public function test_event_stream_captures_the_reconnect_cursor_before_opening_the_stream(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/events');
        $request->headers->set('Last-Event-ID', '01J00000000000000000000008');
        $request->setUserResolver(fn () => $user);

        $response = app(EventStreamController::class)($request, app(WorkspaceEventFeed::class));
        $reflection = new \ReflectionFunction($response->getCallback());
        $usedVariables = $reflection->getStaticVariables();

        self::assertSame('01J00000000000000000000008', $usedVariables['lastEventId']);
    }

    private function event(string $id, string $userId, string $action, string $causationId): void
    {
        DB::table('audit_events')->insert(['id' => $id, 'user_id' => $userId, 'action' => $action, 'origin' => 'worker', 'causation_id' => $causationId, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
