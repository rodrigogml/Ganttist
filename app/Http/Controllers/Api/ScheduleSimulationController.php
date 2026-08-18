<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SchedulingCommandService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ScheduleSimulationController extends Controller
{
    public function __invoke(Request $request, SchedulingCommandService $commands): JsonResponse
    {
        $data = $request->validate([
            'commandId' => ['required', 'string', 'max:64'], 'intent' => ['required', 'array'], 'intent.taskId' => ['required', 'string', 'max:255'],
            'intent.start' => ['required', 'date_format:Y-m-d'], 'intent.finish' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:intent.start'],
        ]);
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project && $integration, 409, 'Conecte o Todoist e selecione um projeto primeiro.');
        try {
            $simulation = $commands->simulate($project, $integration, $data['intent']);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
        $result = $simulation['result'];
        $calendar = $simulation['calendar'];

        return response()->json(['data' => [
            'command_id' => $data['commandId'],
            'changes' => array_map(fn ($id) => ['task_id' => $id, 'start' => $result->tasks[$id]->start?->format('Y-m-d'), 'finish' => $result->tasks[$id]->finish($calendar)->format('Y-m-d')], $result->changedTaskIds),
            'critical_task_ids' => $result->criticalTaskIds, 'total_float' => $result->totalFloat,
            'virtual_starts' => array_map(fn (DateTimeImmutable $date) => $date->format('Y-m-d'), $result->virtualStarts),
        ]]);
    }
}
