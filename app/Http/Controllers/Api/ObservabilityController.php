<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ObservabilityController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'version' => config('app.version', '0.1.0'), 'time' => now()->toIso8601String()]);
    }

    public function readiness(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json(['status' => 'ready', 'checks' => ['database' => 'ok', 'queue_connection' => config('queue.default')]]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'not_ready', 'checks' => ['database' => 'failed']], 503);
        }
    }

    public function metrics(): Response
    {
        $pendingSync = DB::table('sync_operations')->whereIn('state', ['pending', 'applying', 'partial'])->count();
        $oldestPendingSync = DB::table('sync_operations')->whereIn('state', ['pending', 'applying', 'partial'])->min('created_at');
        $oldestPendingSyncSeconds = $oldestPendingSync ? max(0, Carbon::parse($oldestPendingSync)->diffInSeconds(now())) : 0;
        $failedSync = DB::table('sync_operations')->whereIn('state', ['failed', 'conflict'])->count();
        $unprocessedEvents = DB::table('todoist_events')->whereNull('processed_at')->count();
        $reauthorizationRequired = DB::table('todoist_integrations')->where('status', 'reauthorization_required')->count();
        $body = implode("\n", [
            '# TYPE ganttist_sync_operations_pending gauge',
            'ganttist_sync_operations_pending '.$pendingSync,
            '# TYPE ganttist_sync_operations_oldest_pending_seconds gauge',
            'ganttist_sync_operations_oldest_pending_seconds '.$oldestPendingSyncSeconds,
            '# TYPE ganttist_sync_operations_failed gauge',
            'ganttist_sync_operations_failed '.$failedSync,
            '# TYPE ganttist_todoist_events_unprocessed gauge',
            'ganttist_todoist_events_unprocessed '.$unprocessedEvents,
            '# TYPE ganttist_todoist_reauthorization_required gauge',
            'ganttist_todoist_reauthorization_required '.$reauthorizationRequired,
        ])."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
