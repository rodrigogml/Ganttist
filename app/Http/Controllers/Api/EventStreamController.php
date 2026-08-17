<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventStreamController extends Controller
{
    public function __invoke(Request $request)
    {
        $userId = $request->user()->id;

        return response()->stream(function () use ($userId): void {
            set_time_limit(0);
            $lastMarker = null;
            for ($attempt = 0; $attempt < 24; $attempt++) {
                $marker = (string) (DB::table('todoist_integrations')->where('user_id', $userId)->value('updated_at') ?? '');
                if ($marker !== $lastMarker) {
                    echo "event: workspace.updated\ndata: ".json_encode(['updated_at' => $marker], JSON_THROW_ON_ERROR)."\n\n";
                    $lastMarker = $marker;
                } else {
                    echo ": keepalive\n\n";
                }
                if (function_exists('ob_flush')) @ob_flush();
                flush();
                sleep(5);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }
}
