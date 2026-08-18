<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ScheduleOperationController extends Controller
{
    public function show(Request $request, string $operationId): JsonResponse
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');
        $operation = DB::table('recalculations')->where('id', $operationId)->where('gantt_project_id', $project->id)->first();
        abort_unless($operation, 404, 'Operação não encontrada.');
        $items = DB::table('recalculation_items')->where('recalculation_id', $operation->id)->orderBy('sequence')->get()->map(fn (object $item): array => ['task_id' => $item->todoist_task_id, 'state' => $item->state, 'attempts' => $item->attempts, 'error' => $item->error])->all();

        return response()->json(['data' => ['operation_id' => $operation->id, 'command_id' => $operation->command_id, 'state' => $operation->state, 'error' => $operation->error, 'items' => $items]]);
    }
}
