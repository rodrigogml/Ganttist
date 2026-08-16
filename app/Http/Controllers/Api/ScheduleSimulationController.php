<?php

namespace App\Http\Controllers\Api;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\WorkCalendar;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScheduleSimulationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'today' => ['required', 'date_format:Y-m-d'], 'tasks' => ['required', 'array', 'max:5000'], 'tasks.*.id' => ['required', 'string'],
            'tasks.*.title' => ['required', 'string'], 'tasks.*.start' => ['nullable', 'date_format:Y-m-d'], 'tasks.*.duration' => ['required', 'integer', 'min:1', 'max:3650'],
            'tasks.*.completed' => ['sometimes', 'boolean'], 'dependencies' => ['array'], 'dependencies.*.from' => ['required', 'string'],
            'dependencies.*.to' => ['required', 'string'], 'dependencies.*.type' => ['required', 'in:FS,SS,FF,SF'],
        ]);
        $tasks = array_map(fn ($t) => new TaskPlan($t['id'], $t['title'], isset($t['start']) ? new DateTimeImmutable($t['start']) : null, $t['duration'], $t['completed'] ?? false), $data['tasks']);
        $dependencies = array_map(fn ($d) => new Dependency($d['from'], $d['to'], $d['type']), $data['dependencies'] ?? []);
        $calendar = new WorkCalendar;
        $result = (new SchedulingEngine($calendar))->schedule($tasks, $dependencies, new DateTimeImmutable($data['today']));

        return response()->json(['data' => [
            'changes' => array_map(fn ($id) => ['task_id' => $id, 'start' => $result->tasks[$id]->start?->format('Y-m-d'), 'finish' => $result->tasks[$id]->finish($calendar)->format('Y-m-d')], $result->changedTaskIds),
            'critical_task_ids' => $result->criticalTaskIds, 'total_float' => $result->totalFloat,
        ]]);
    }
}
