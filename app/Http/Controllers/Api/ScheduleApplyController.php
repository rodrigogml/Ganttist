<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRecalculation;
use App\Services\AuditWriter;
use App\Services\SchedulingCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ScheduleApplyController extends Controller
{
    public function __invoke(Request $request, SchedulingCommandService $commands, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['commandId' => ['required', 'string', 'max:64'], 'intent' => ['required', 'array'], 'intent.taskId' => ['required', 'string', 'max:255'], 'intent.start' => ['required', 'date_format:Y-m-d'], 'intent.finish' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:intent.start']]);
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project && $integration, 409, 'Conecte o Todoist e selecione um projeto primeiro.');
        if ($existing = DB::table('recalculations')->where('command_id', $data['commandId'])->first()) {
            abort_unless($existing->gantt_project_id === $project->id, 404);

            return response()->json(['data' => ['operation_id' => $existing->id, 'command_id' => $existing->command_id, 'state' => $existing->state, 'idempotent' => true]]);
        }
        try {
            $simulation = $commands->simulate($project, $integration, $data['intent']);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
        $operationId = (string) Str::ulid();
        $result = $simulation['result'];
        $calendar = $simulation['calendar'];
        DB::transaction(function () use ($operationId, $project, $data, $result, $calendar, $simulation, $audit, $request): void {
            DB::table('recalculations')->insert(['id' => $operationId, 'gantt_project_id' => $project->id, 'command_id' => $data['commandId'], 'mode' => 'MANUAL', 'state' => 'pending', 'summary' => json_encode(['changes' => count($result->changedTaskIds), 'intent' => $data['intent']], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($result->changedTaskIds as $sequence => $taskId) {
                $task = $result->tasks[$taskId];
                if ($task->completed || $task->start === null) {
                    continue;
                }
                $before = $simulation['before_tasks'][$taskId] ?? null;
                $beforeState = ['start' => $before?->start?->format('Y-m-d'), 'finish' => $before?->start !== null ? $before->finish($calendar)->format('Y-m-d') : null];
                DB::table('recalculation_items')->insert(['id' => (string) Str::ulid(), 'recalculation_id' => $operationId, 'sequence' => $sequence, 'todoist_task_id' => $taskId, 'before_state' => json_encode($beforeState, JSON_THROW_ON_ERROR), 'after_state' => json_encode(['start' => $task->start->format('Y-m-d'), 'finish' => $task->finish($calendar)->format('Y-m-d')], JSON_THROW_ON_ERROR), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $project->id, 'command_id' => $data['commandId'], 'operation' => 'recalculation.apply', 'state' => 'pending', 'payload' => json_encode(['recalculation_id' => $operationId], JSON_THROW_ON_ERROR), 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $audit->record((string) $request->user()->id, $project->id, 'recalculation.created', 'user', 'recalculation', $operationId, $data['commandId'], null, ['state' => 'pending', 'items' => count($result->changedTaskIds)]);
        });
        if (config('queue.default') !== 'sync') {
            ProcessRecalculation::dispatch($operationId)->onQueue('planning');
        }

        return response()->json(['data' => ['operation_id' => $operationId, 'command_id' => $data['commandId'], 'state' => 'pending', 'items' => count($result->changedTaskIds)]], 202);
    }
}
