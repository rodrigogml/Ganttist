<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WorkspaceController extends Controller
{
    public function show(Request $request, TodoistGateway $gateway): JsonResponse
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        Log::debug('workspace.requested', ['user_id' => $request->user()->id, 'has_project' => (bool) $project, 'has_integration' => (bool) $integration]);
        if ($project && $integration) {
            $snapshot = $gateway->projectSnapshot(decrypt($integration->access_token_encrypted), $project->todoist_project_id);
            Log::debug('workspace.todoist.snapshot_loaded', ['user_id' => $request->user()->id, 'project_id' => $project->id]);

            return response()->json($this->fromTodoist($project, $snapshot));
        }

        abort_unless(config('services.todoist.demo_mode'), 409, 'Conecte o Todoist e selecione um projeto primeiro.');

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
        $nodes = [];
        foreach ($tasks as $task) {
            $id = (string) $task['id'];
            $nodes[$id] = ['task' => $task, 'parent_id' => ! empty($task['parent_id']) ? (string) $task['parent_id'] : null, 'section_id' => ! empty($task['section_id']) ? (string) $task['section_id'] : null];
        }
        $children = [];
        $roots = [];
        foreach ($nodes as $id => $node) {
            if ($node['parent_id'] && isset($nodes[$node['parent_id']])) $children[$node['parent_id']][] = $id;
            else $roots[] = $id;
        }
        $sortIds = function (array &$ids) use ($nodes): void {
            usort($ids, fn (string $left, string $right): int => ((int) ($nodes[$left]['task']['child_order'] ?? $nodes[$left]['task']['order'] ?? 0)) <=> ((int) ($nodes[$right]['task']['child_order'] ?? $nodes[$right]['task']['order'] ?? 0)));
        };
        $sortIds($roots);
        foreach ($children as &$ids) $sortIds($ids);
        unset($ids);
        $mapTask = function (string $id, int $level, ?string $displayParentId) use (&$mapTask, $nodes, $children): array {
            $task = $nodes[$id]['task'];
            $start = $task['due']['date'] ?? null;
            $finish = $task['deadline_date'] ?? $start;
            $item = ['id' => $id, 'title' => (string) $task['content'], 'kind' => 'task', 'level' => $level, 'parent_id' => $displayParentId, 'has_children' => ! empty($children[$id]), 'start' => $start, 'finish' => $finish, 'progress' => ($task['is_completed'] ?? false) ? 100 : 0, 'status' => ($task['is_completed'] ?? false) ? 'completed' : ($start ? 'not_started' : 'unscheduled'), 'critical' => false, 'priority' => (int) ($task['priority'] ?? 1), 'assignee' => null];
            $result = [$item];
            foreach ($children[$id] ?? [] as $childId) $result = [...$result, ...$mapTask($childId, $level + 1, $id)];

            return $result;
        };
        $rootsBySection = [];
        $unsectionedRoots = [];
        foreach ($roots as $id) {
            $sectionId = $nodes[$id]['section_id'];
            if ($sectionId) $rootsBySection[$sectionId][] = $id;
            else $unsectionedRoots[] = $id;
        }
        $ordered = [];
        foreach ($sections as $section) {
            $sectionId = (string) $section['id'];
            $groupId = 'section:'.$sectionId;
            $members = [];
            foreach ($rootsBySection[$sectionId] ?? [] as $rootId) $members = [...$members, ...$mapTask($rootId, 0, $groupId)];
            $dates = array_values(array_filter(array_merge(array_column($members, 'start'), array_column($members, 'finish'))));
            $ordered[] = ['id' => $groupId, 'title' => (string) $section['name'], 'kind' => 'group', 'level' => 0, 'parent_id' => null, 'has_children' => $members !== [], 'start' => $dates ? min($dates) : null, 'finish' => $dates ? max($dates) : null, 'progress' => count($members) ? (int) round(count(array_filter($members, fn (array $task): bool => $task['status'] === 'completed')) / count($members) * 100) : 0, 'status' => 'running', 'critical' => false];
            $ordered = [...$ordered, ...$members];
        }
        foreach ($unsectionedRoots as $rootId) $ordered = [...$ordered, ...$mapTask($rootId, 0, null)];
        $leafTasks = array_values(array_filter($ordered, fn (array $task): bool => $task['kind'] === 'task'));
        $completed = count(array_filter($leafTasks, fn (array $task): bool => $task['status'] === 'completed'));
        $total = count($leafTasks);

        $dependencies = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->get()->map(fn (object $dependency): array => ['id' => $dependency->id, 'from' => $dependency->predecessor_todoist_task_id, 'to' => $dependency->successor_todoist_task_id, 'type' => $dependency->type, 'critical' => false])->values()->all();
        return ['data' => ['project' => ['id' => $project->id, 'name' => $project->display_name, 'source' => 'Todoist', 'sync_status' => 'synced', 'updated_at' => now()->toIso8601String()], 'calendar' => ['timezone' => 'America/Sao_Paulo', 'working_days' => [1, 2, 3, 4, 5], 'exceptions' => []], 'tasks' => $ordered, 'dependencies' => $dependencies, 'stats' => ['progress' => $total ? (int) round($completed / $total * 100) : 0, 'completed' => $completed, 'total' => $total, 'critical' => 0, 'unscheduled' => count(array_filter($leafTasks, fn (array $task): bool => $task['status'] === 'unscheduled'))]], 'meta' => ['demo' => false, 'message' => 'Dados sincronizados do Todoist.']];
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
