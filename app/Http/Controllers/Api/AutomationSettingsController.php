<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyProjectAutomations;
use App\Services\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AutomationSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->project($request)->id)]);
    }

    public function update(Request $request, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate([
            'commandId' => ['required', 'string', 'max:64'],
            'expectedVersion' => ['required', 'integer', 'min:1'],
            'autoScheduleBlockedTasks' => ['required', 'boolean'],
            'clearParentTaskDates' => ['required', 'boolean'],
        ]);
        $project = $this->project($request);
        $before = DB::transaction(function () use ($project, $data, $request, $audit): array {
            $settings = DB::table('project_settings')->where('gantt_project_id', $project->id)->first();
            abort_unless($settings, 409, 'Configuração do projeto indisponível.');
            abort_if(! property_exists($settings, 'clearParentTaskDates') && $data['clearParentTaskDates'], 503, 'A atualização de banco necessária para esta automação ainda não foi aplicada.');
            $before = [
                'autoScheduleBlockedTasks' => (bool) $settings->autoScheduleBlockedTasks,
                'clearParentTaskDates' => (bool) ($settings->clearParentTaskDates ?? false),
            ];
            $updates = [
                'autoScheduleBlockedTasks' => $data['autoScheduleBlockedTasks'],
                'automationVersion' => $settings->automationVersion + 1,
                'updated_at' => now(),
            ];
            if (property_exists($settings, 'clearParentTaskDates')) {
                $updates['clearParentTaskDates'] = $data['clearParentTaskDates'];
            }
            $updated = DB::table('project_settings')
                ->where('id', $settings->id)
                ->where('automationVersion', $data['expectedVersion'])
                ->update($updates);
            abort_unless($updated === 1, 409, 'As configurações de automação foram alteradas em outro contexto. Recarregue antes de salvar.');
            $audit->record($request->user()->id, $project->id, 'automation.settings.updated', 'web', 'project_settings', $project->id, $data['commandId'], $before, [
                'autoScheduleBlockedTasks' => $data['autoScheduleBlockedTasks'],
                'clearParentTaskDates' => $data['clearParentTaskDates'],
            ]);

            return $before;
        });
        $enabledAutomation = ($data['autoScheduleBlockedTasks'] && ! $before['autoScheduleBlockedTasks'])
            || ($data['clearParentTaskDates'] && ! $before['clearParentTaskDates']);
        if ($enabledAutomation) {
            try {
                ApplyProjectAutomations::dispatch($project->id)->onQueue('sync');
            } catch (\Throwable $exception) {
                Log::warning('project.automation.dispatch_failed', ['project_id' => $project->id, 'exception' => $exception::class]);
            }
        }

        return response()->json(['data' => $this->payload($project->id)]);
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

        return [
            'version' => (int) $settings->automationVersion,
            'autoScheduleBlockedTasks' => (bool) $settings->autoScheduleBlockedTasks,
            'clearParentTaskDates' => (bool) ($settings->clearParentTaskDates ?? false),
        ];
    }
}
