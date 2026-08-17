<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\WorkCalendar;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ScheduleApplyController extends Controller
{
    public function __invoke(Request $request, TodoistGateway $gateway): JsonResponse
    {
        $data = $request->validate(['today' => ['required', 'date_format:Y-m-d'], 'tasks' => ['required', 'array', 'max:5000'], 'tasks.*.id' => ['required', 'string'], 'tasks.*.title' => ['required', 'string'], 'tasks.*.start' => ['nullable', 'date_format:Y-m-d'], 'tasks.*.duration' => ['required', 'integer', 'min:1', 'max:3650'], 'tasks.*.completed' => ['sometimes', 'boolean'], 'dependencies' => ['array'], 'dependencies.*.from' => ['required', 'string'], 'dependencies.*.to' => ['required', 'string'], 'dependencies.*.type' => ['required', 'in:FS,SS,FF,SF']]);
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project && $integration, 409, 'Conecte o Todoist e selecione um projeto primeiro.');
        $tasks = array_map(fn (array $task): TaskPlan => new TaskPlan($task['id'], $task['title'], isset($task['start']) && $task['start'] ? new DateTimeImmutable($task['start']) : null, $task['duration'], $task['completed'] ?? false), $data['tasks']);
        $dependencies = array_map(fn (array $dependency): Dependency => new Dependency($dependency['from'], $dependency['to'], $dependency['type']), $data['dependencies'] ?? []);
        $result = (new SchedulingEngine(new WorkCalendar))->schedule($tasks, $dependencies, new DateTimeImmutable($data['today']));
        $commandId = (string) Str::ulid();
        $updates = [];
        try {
            $token = decrypt($integration->access_token_encrypted);
            foreach ($result->changedTaskIds as $taskId) {
                $planned = $result->tasks[$taskId];
                $start = $planned->start?->format('Y-m-d');
                if (! $start || $planned->completed) continue;
                $updates[] = ['task_id' => $taskId, 'start' => $start, 'finish' => $planned->finish(new WorkCalendar)->format('Y-m-d')];
                $gateway->updateTaskDates($token, $taskId, $start, $planned->finish(new WorkCalendar)->format('Y-m-d'));
            }
            DB::table('recalculations')->insert(['id' => $commandId, 'gantt_project_id' => $project->id, 'command_id' => $commandId, 'mode' => 'MANUAL', 'state' => 'applied', 'summary' => json_encode(['changes' => count($updates)], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $exception) {
            DB::table('recalculations')->insert(['id' => $commandId, 'gantt_project_id' => $project->id, 'command_id' => $commandId, 'mode' => 'MANUAL', 'state' => 'failed', 'summary' => json_encode(['applied' => count($updates)], JSON_THROW_ON_ERROR), 'error' => $exception::class, 'created_at' => now(), 'updated_at' => now()]);
            throw $exception;
        }

        return response()->json(['data' => ['command_id' => $commandId, 'state' => 'applied', 'changes' => $updates]]);
    }
}
