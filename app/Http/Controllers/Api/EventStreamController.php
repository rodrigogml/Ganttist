<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceEventFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventStreamController extends Controller
{
    public function __invoke(Request $request, WorkspaceEventFeed $feed)
    {
        $userId = $request->user()->id;

        return response()->stream(function () use ($userId, $feed): void {
            set_time_limit(0);
            $lastEventId = $this->cursor($request, $userId);
            for ($attempt = 0; $attempt < 24; $attempt++) {
                $events = $feed->after($userId, $lastEventId);
                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        echo "id: {$event->id}\nevent: workspace.updated\ndata: ".json_encode($feed->payload($event), JSON_THROW_ON_ERROR)."\n\n";
                        $lastEventId = $event->id;
                    }
                } else {
                    echo ": keepalive\n\n";
                }
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                sleep(5);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }

    private function cursor(Request $request, string $userId): string
    {
        $lastEventId = trim((string) $request->header('Last-Event-ID'));

        return $lastEventId !== ''
            ? $lastEventId
            : (string) (DB::table('audit_events')->where('user_id', $userId)->max('id') ?? '');
    }
}
