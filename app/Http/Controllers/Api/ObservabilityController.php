<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
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
        $projects = DB::table('projects')->count();
        $tasks = DB::table('project_tasks')->count();
        $overdue = DB::table('project_tasks')->whereNull('completed_at')->whereNotNull('planned_finish')->where('planned_finish', '<', now()->toDateString())->count();
        $pendingInvitations = DB::table('project_invitations')->where('status', 'pending')->count();
        $body = implode("\n", [
            '# TYPE ganttist_projects_total gauge',
            'ganttist_projects_total '.$projects,
            '# TYPE ganttist_tasks_total gauge',
            'ganttist_tasks_total '.$tasks,
            '# TYPE ganttist_tasks_overdue gauge',
            'ganttist_tasks_overdue '.$overdue,
            '# TYPE ganttist_invitations_pending gauge',
            'ganttist_invitations_pending '.$pendingInvitations,
        ])."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
