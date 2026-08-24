<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRecalculation;
use App\Services\AuditWriter;
use App\Services\CalendarSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CalendarController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->project($request)->id)]);
    }

    public function simulate(Request $request, CalendarSimulationService $simulations): JsonResponse
    {
        $data = $this->validated($request);
        $project = $this->project($request);
        $integration = $this->integration($request);
        abort_unless($integration, 409, 'Conecte o Todoist para simular impactos do calendário.');
        $result = $simulations->simulate($project, $integration, $data);

        return response()->json(['data' => ['command_id' => $data['commandId'], 'changes' => $result['changes']]]);
    }

    public function update(Request $request, CalendarSimulationService $simulations, AuditWriter $audit): JsonResponse
    {
        $data = $this->validated($request);
        $project = $this->project($request);
        $integration = $this->integration($request);
        $simulation = $integration ? $simulations->simulate($project, $integration, $data) : ['changes' => []];
        if ($simulation['changes'] !== [] && $data['reschedulingMode'] === 'MANUAL' && ! ($data['confirmed'] ?? false)) {
            abort(422, 'Simule e confirme os impactos do calendário antes de salvar.');
        }
        abort_if($simulation['changes'] !== [] && ! $integration, 409, 'Conecte o Todoist para aplicar os impactos do calendário.');
        $operationId = $simulation['changes'] === [] ? null : (string) Str::ulid();
        $updated = DB::transaction(function () use ($project, $data, $simulation, $operationId, $request, $audit): bool {
            $settings = DB::table('project_settings')->where('gantt_project_id', $project->id)->first();
            abort_unless($settings, 409, 'Configuração do projeto indisponível.');
            $workDays = array_fill_keys($data['workingDays'], true);
            $values = ['rescheduling_mode' => $data['reschedulingMode'], 'projection_policy' => $data['projectionPolicy'], 'non_working_deadline_policy' => $data['deadlinePolicy'], 'allow_unscheduled_tasks' => $data['allowUnscheduledTasks'], 'version' => $settings->version + 1, 'updated_at' => now()];
            foreach (self::DAYS as $day) {
                $values[$day] = isset($workDays[$day]);
            }
            if (DB::table('project_settings')->where('id', $settings->id)->where('version', $data['expectedVersion'])->update($values) !== 1) {
                return false;
            }
            DB::table('calendar_exceptions')->where('gantt_project_id', $project->id)->delete();
            foreach ($data['exceptions'] as $exception) {
                DB::table('calendar_exceptions')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $project->id, 'date' => $exception['date'], 'type' => $exception['type'], 'description' => $exception['description'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($operationId !== null) {
                DB::table('recalculations')->insert(['id' => $operationId, 'gantt_project_id' => $project->id, 'command_id' => $data['commandId'], 'mode' => $data['reschedulingMode'], 'state' => 'pending', 'summary' => json_encode(['changes' => count($simulation['changes']), 'source' => 'calendar'], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($simulation['changes'] as $sequence => $change) {
                    DB::table('recalculation_items')->insert(['id' => (string) Str::ulid(), 'recalculation_id' => $operationId, 'sequence' => $sequence, 'todoist_task_id' => $change['task_id'], 'before_state' => json_encode($change['before'], JSON_THROW_ON_ERROR), 'after_state' => json_encode($change['after'], JSON_THROW_ON_ERROR), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('sync_operations')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $project->id, 'command_id' => $data['commandId'], 'operation' => 'calendar.recalculation.apply', 'state' => 'pending', 'payload' => json_encode(['recalculation_id' => $operationId], JSON_THROW_ON_ERROR), 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $audit->record($request->user()->id, $project->id, 'calendar.updated', 'web', 'project_settings', $project->id, $data['commandId'], null, ['operation_id' => $operationId]);

            return true;
        });
        abort_unless($updated, 409, 'O calendário foi alterado em outro contexto. Recarregue antes de salvar.');
        if ($operationId !== null && config('queue.default') !== 'sync') {
            ProcessRecalculation::dispatch($operationId)->onQueue('planning');
        }

        return response()->json(['data' => $this->payload($project->id), 'operation_id' => $operationId], $operationId === null ? 200 : 202);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'commandId' => ['required', 'string', 'max:64'], 'expectedVersion' => ['required', 'integer', 'min:1'],
            'workingDays' => ['required', 'array', 'min:1'], 'workingDays.*' => ['string', 'in:'.implode(',', self::DAYS)],
            'reschedulingMode' => ['required', 'in:MANUAL,AUTOMATIC'], 'projectionPolicy' => ['required', 'in:PRESERVE_DURATION,PRESERVE_DEADLINE'], 'deadlinePolicy' => ['required', 'in:ANTERIOR,POSTERIOR'], 'allowUnscheduledTasks' => ['required', 'boolean'],
            'exceptions' => ['present', 'array'], 'exceptions.*.date' => ['required', 'date_format:Y-m-d'], 'exceptions.*.type' => ['required', 'in:NON_WORKING,WORKING'], 'exceptions.*.description' => ['nullable', 'string', 'max:255'], 'confirmed' => ['sometimes', 'boolean'],
        ]);
    }

    private function integration(Request $request): ?object
    {
        return DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
    }

    private function project(Request $request): object
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');

        return $project;
    }

    private function payload(string $projectId): array
    {
        $settings = DB::table('project_settings')->where('gantt_project_id', $projectId)->first();
        abort_unless($settings, 409, 'Configuração do projeto indisponível.');

        return ['version' => $settings->version, 'workingDays' => array_values(array_filter(self::DAYS, fn (string $day): bool => (bool) $settings->{$day})), 'reschedulingMode' => $settings->rescheduling_mode, 'projectionPolicy' => $settings->projection_policy, 'deadlinePolicy' => $settings->non_working_deadline_policy, 'allowUnscheduledTasks' => (bool) $settings->allow_unscheduled_tasks, 'exceptions' => DB::table('calendar_exceptions')->where('gantt_project_id', $projectId)->orderBy('date')->get()->map(fn (object $exception): array => ['date' => $exception->date, 'type' => $exception->type, 'description' => $exception->description])->all()];
    }
}
