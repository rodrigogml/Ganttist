<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkspaceController extends Controller
{
    public function show(Request $request, TodoistGateway $gateway): JsonResponse
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        if ($project && $integration) {
            $snapshot = $gateway->projectSnapshot(decrypt($integration->access_token_encrypted), $project->todoist_project_id);

            return response()->json($this->fromTodoist($project, $snapshot));
        }

        return response()->json(['data' => [
            'project' => ['id' => 'demo-product-launch', 'name' => 'Lançamento do Ganttist', 'source' => 'Todoist', 'sync_status' => 'synced', 'updated_at' => now()->toIso8601String()],
            'calendar' => ['timezone' => 'America/Sao_Paulo', 'working_days' => [1, 2, 3, 4, 5], 'exceptions' => [['date' => '2026-08-21', 'type' => 'NON_WORKING', 'description' => 'Feriado local']]],
            'tasks' => $this->tasks(),
            'dependencies' => [
                ['id' => 'd1', 'from' => 't1', 'to' => 't2', 'type' => 'FS', 'critical' => true], ['id' => 'd2', 'from' => 't2', 'to' => 't3', 'type' => 'FS', 'critical' => true],
                ['id' => 'd3', 'from' => 't3', 'to' => 't5', 'type' => 'FS', 'critical' => true], ['id' => 'd4', 'from' => 't4', 'to' => 't5', 'type' => 'FF', 'critical' => false],
                ['id' => 'd5', 'from' => 't5', 'to' => 't7', 'type' => 'FS', 'critical' => true], ['id' => 'd6', 'from' => 't6', 'to' => 't7', 'type' => 'SS', 'critical' => false],
            ],
            'stats' => ['progress' => 38, 'completed' => 3, 'total' => 12, 'critical' => 5, 'unscheduled' => 1],
        ], 'meta' => ['demo' => true, 'message' => 'Fixture local; conecte o OAuth Todoist para usar dados reais.']]);
    }

    private function fromTodoist(object $project, array $snapshot): array
    {
        $tasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'];
        $sections = $snapshot['sections']['results'] ?? $snapshot['sections'];
        $bySection = [];
        foreach ($sections as $section) $bySection[$section['id']] = $section['name'];
        $mapped = [];
        foreach ($tasks as $task) {
            $start = $task['due']['date'] ?? null;
            $finish = $task['deadline_date'] ?? $start;
            $mapped[] = [
                'id' => (string) $task['id'], 'title' => (string) $task['content'], 'kind' => 'task', 'level' => $task['parent_id'] ? 1 : 0,
                'start' => $start, 'finish' => $finish, 'progress' => ($task['is_completed'] ?? false) ? 100 : 0,
                'status' => ($task['is_completed'] ?? false) ? 'completed' : ($start ? 'not_started' : 'unscheduled'), 'critical' => false,
                'priority' => (int) ($task['priority'] ?? 1), 'assignee' => null, 'section' => $bySection[$task['section_id'] ?? ''] ?? null,
            ];
        }
        $completed = count(array_filter($mapped, fn (array $task): bool => $task['status'] === 'completed'));
        $total = count($mapped);
        $groups = [];
        foreach (array_values(array_unique(array_filter(array_column($mapped, 'section')))) as $section) {
            $members = array_values(array_filter($mapped, fn (array $task): bool => $task['section'] === $section));
            $dates = array_values(array_filter(array_merge(array_column($members, 'start'), array_column($members, 'finish'))));
            $groups[] = ['id' => 'section:'.sha1($section), 'title' => $section, 'kind' => 'group', 'level' => 0, 'start' => $dates ? min($dates) : null, 'finish' => $dates ? max($dates) : null, 'progress' => count($members) ? (int) round(count(array_filter($members, fn (array $task): bool => $task['status'] === 'completed')) / count($members) * 100) : 0, 'status' => 'running', 'critical' => false];
        }
        $ordered = [];
        foreach ($groups as $group) {
            $ordered[] = $group;
            foreach ($mapped as $task) {
                if ($task['section'] === $group['title']) {
                    $task['level'] = 1;
                    unset($task['section']);
                    $ordered[] = $task;
                }
            }
        }
        foreach ($mapped as $task) {
            if ($task['section'] === null) {
                unset($task['section']);
                $ordered[] = $task;
            }
        }

        return ['data' => ['project' => ['id' => $project->id, 'name' => $project->display_name, 'source' => 'Todoist', 'sync_status' => 'synced', 'updated_at' => now()->toIso8601String()], 'calendar' => ['timezone' => 'America/Sao_Paulo', 'working_days' => [1, 2, 3, 4, 5], 'exceptions' => []], 'tasks' => $ordered, 'dependencies' => [], 'stats' => ['progress' => $total ? (int) round($completed / $total * 100) : 0, 'completed' => $completed, 'total' => $total, 'critical' => 0, 'unscheduled' => count(array_filter($mapped, fn (array $task): bool => $task['status'] === 'unscheduled'))]], 'meta' => ['demo' => false, 'message' => 'Dados sincronizados do Todoist.']];
    }

    private function tasks(): array
    {
        return [
            ['id' => 'g1', 'title' => 'Estratégia & Descoberta', 'kind' => 'group', 'level' => 0, 'start' => '2026-08-17', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true],
            ['id' => 't1', 'title' => 'Mapear jornada principal', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-17', 'finish' => '2026-08-18', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 4, 'assignee' => 'MC'],
            ['id' => 't2', 'title' => 'Validar arquitetura técnica', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-19', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 3, 'assignee' => 'RL'],
            ['id' => 'g2', 'title' => 'Experiência do produto', 'kind' => 'group', 'level' => 0, 'start' => '2026-08-24', 'finish' => '2026-09-03', 'progress' => 36, 'status' => 'running', 'critical' => true],
            ['id' => 't3', 'title' => 'Design system e fundações', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-24', 'finish' => '2026-08-27', 'progress' => 70, 'status' => 'running', 'critical' => true, 'priority' => 4, 'assignee' => 'AS'],
            ['id' => 't4', 'title' => 'Prototipar navegação mobile', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-25', 'finish' => '2026-08-28', 'progress' => 45, 'status' => 'running', 'critical' => false, 'priority' => 2, 'assignee' => 'CB'],
            ['id' => 't5', 'title' => 'Renderizador Gantt próprio', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-28', 'finish' => '2026-09-03', 'progress' => 18, 'status' => 'running', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 'g3', 'title' => 'Integrações & Qualidade', 'kind' => 'group', 'level' => 0, 'start' => '2026-09-04', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'not_started', 'critical' => true],
            ['id' => 't6', 'title' => 'OAuth e sincronização Todoist', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-08', 'progress' => 0, 'status' => 'not_started', 'critical' => false, 'priority' => 3, 'assignee' => 'MC'],
            ['id' => 't7', 'title' => 'Golden tests do scheduling', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-09', 'progress' => 0, 'status' => 'not_started', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 't8', 'title' => 'Homologação do fluxo completo', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-10', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'not_started', 'critical' => true, 'priority' => 3, 'assignee' => 'AS'],
            ['id' => 't9', 'title' => 'Definir campanha de lançamento', 'kind' => 'task', 'level' => 0, 'start' => null, 'finish' => null, 'progress' => 0, 'status' => 'unscheduled', 'critical' => false, 'priority' => 1, 'assignee' => 'CB'],
        ];
    }
}
