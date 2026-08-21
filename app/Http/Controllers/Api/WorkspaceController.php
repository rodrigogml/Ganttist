<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\GroupScheduleCalculator;
use App\Domain\Scheduling\ProjectionPolicy;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\TaskProjectionCalculator;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Domain\Scheduling\WorkCalendar;
use App\Http\Controllers\Controller;
use App\Services\ProjectCalendarService;
use App\Services\TodoistAccessTokenService;
use App\Services\TodoistSnapshotStore;
use App\Support\TodoistTask;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WorkspaceController extends Controller
{
    public function show(Request $request, TodoistGateway $gateway, ProjectCalendarService $calendars, TodoistAccessTokenService $tokens, TodoistSnapshotStore $snapshots): JsonResponse
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        Log::debug('workspace.requested', ['user_id' => $request->user()->id, 'has_project' => (bool) $project, 'has_integration' => (bool) $integration]);
        if ($project && $integration) {
            $snapshot = $snapshots->get($project->id);
            if ($snapshot === null) {
                $snapshot = $gateway->projectSnapshot($tokens->accessToken($integration), $project->todoist_project_id);
                Log::debug('workspace.todoist.snapshot_loaded', ['user_id' => $request->user()->id, 'project_id' => $project->id, 'source' => 'remote']);
            } else {
                Log::debug('workspace.todoist.snapshot_loaded', ['user_id' => $request->user()->id, 'project_id' => $project->id, 'source' => 'reconciled_cache']);
            }

            return response()->json($this->fromTodoist($project, $integration, $snapshot, $calendars->forProject($project->id)));
        }

        abort_unless(config('services.todoist.demo_mode'), 409, 'Conecte o Todoist e selecione um projeto primeiro.');

        return response()->json(['data' => [
            'project' => ['id' => 'demo-product-launch', 'name' => 'Lançamento do Ganttist', 'source' => 'Todoist', 'sync_status' => 'synced', 'updated_at' => now()->toIso8601String()],
            'calendar' => ['timezone' => 'America/Sao_Paulo', 'working_days' => [1, 2, 3, 4, 5], 'projection_policy' => 'PRESERVE_DURATION', 'exceptions' => [['date' => '2026-08-21', 'type' => 'NON_WORKING', 'description' => 'Feriado local']]],
            'tasks' => $this->tasks(),
            'dependencies' => [
                ['id' => 'd1', 'from' => 't1', 'to' => 't2', 'type' => 'FS', 'critical' => true], ['id' => 'd2', 'from' => 't2', 'to' => 't3', 'type' => 'FS', 'critical' => true],
                ['id' => 'd3', 'from' => 't3', 'to' => 't5', 'type' => 'FS', 'critical' => true], ['id' => 'd4', 'from' => 't4', 'to' => 't5', 'type' => 'FF', 'critical' => false],
                ['id' => 'd5', 'from' => 't5', 'to' => 't7', 'type' => 'FS', 'critical' => true], ['id' => 'd6', 'from' => 't6', 'to' => 't7', 'type' => 'SS', 'critical' => false],
            ],
            'stats' => ['progress' => 25, 'completed' => 2, 'total' => 9, 'critical' => 5, 'opened' => 1, 'blocked' => 3, 'scheduled' => 3, 'late' => 0, 'without_dates' => 1],
        ], 'meta' => ['demo' => true, 'message' => 'Fixture local; conecte o OAuth Todoist para usar dados reais.']]);
    }

    private function fromTodoist(object $project, object $integration, array $snapshot, WorkCalendar $calendar): array
    {
        $tasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'];
        $sections = $snapshot['sections']['results'] ?? $snapshot['sections'];
        $settings = DB::table('project_settings')->where('gantt_project_id', $project->id)->first();
        $projectionPolicy = ProjectionPolicy::fromSetting($settings?->projection_policy ?? null);
        $timezone = 'America/Sao_Paulo';
        $today = now($timezone)->startOfDay()->toDateTimeImmutable();
        $completionOverrides = DB::table('task_metadata')
            ->where('gantt_project_id', $project->id)
            ->whereNotNull('completion_date_override')
            ->pluck('completion_date_override', 'todoist_task_id')
            ->all();
        $nodes = [];
        foreach ($tasks as $task) {
            $id = (string) $task['id'];
            $nodes[$id] = ['task' => $task, 'parent_id' => ! empty($task['parent_id']) ? (string) $task['parent_id'] : null, 'section_id' => ! empty($task['section_id']) ? (string) $task['section_id'] : null];
        }
        $children = [];
        $roots = [];
        foreach ($nodes as $id => $node) {
            if ($node['parent_id'] && isset($nodes[$node['parent_id']])) {
                $children[$node['parent_id']][] = $id;
            } else {
                $roots[] = $id;
            }
        }
        $sortIds = function (array &$ids) use ($nodes): void {
            usort($ids, fn (string $left, string $right): int => ((int) ($nodes[$left]['task']['child_order'] ?? $nodes[$left]['task']['order'] ?? 0)) <=> ((int) ($nodes[$right]['task']['child_order'] ?? $nodes[$right]['task']['order'] ?? 0)));
        };
        $sortIds($roots);
        foreach ($children as &$ids) {
            $sortIds($ids);
        }
        unset($ids);
        $mapTask = function (string $id, int $level, ?string $displayParentId) use (&$mapTask, $nodes, $children, $calendar, $completionOverrides, $timezone): array {
            $task = $nodes[$id]['task'];
            $start = TodoistTask::start($task);
            $finish = TodoistTask::deadline($task);
            $completed = TodoistTask::completed($task);
            $completionDate = $completionOverrides[$id] ?? TodoistTask::completionDate($task, $timezone);
            $description = trim((string) ($task['description'] ?? ''));
            $item = ['id' => $id, 'title' => (string) $task['content'], 'description' => $description !== '' ? $description : null, 'kind' => 'task', 'level' => $level, 'parent_id' => $displayParentId, 'has_children' => ! empty($children[$id]), 'start' => $start, 'finish' => $finish, 'considered_start' => null, 'considered_deadline' => null, 'unlock_date' => null, 'completed' => $completed, 'planned' => $start !== null, 'derived' => false, 'virtual_start' => null, 'effective_completion' => $completionDate, 'calendar_inconsistent' => $start !== null && ! $calendar->isWorkDay(new DateTimeImmutable($start)), 'sync_status' => 'synced', 'progress' => $completed ? 100 : 0, 'status' => $completed ? 'completed' : 'opened', 'critical' => false, 'priority' => (int) ($task['priority'] ?? 1), 'assignee' => null];
            $result = [$item];
            foreach ($children[$id] ?? [] as $childId) {
                $result = [...$result, ...$mapTask($childId, $level + 1, $id)];
            }

            return $result;
        };
        $rootsBySection = [];
        $unsectionedRoots = [];
        foreach ($roots as $id) {
            $sectionId = $nodes[$id]['section_id'];
            if ($sectionId) {
                $rootsBySection[$sectionId][] = $id;
            } else {
                $unsectionedRoots[] = $id;
            }
        }
        $ordered = [];
        foreach ($sections as $section) {
            $sectionId = (string) $section['id'];
            $groupId = 'section:'.$sectionId;
            $members = [];
            foreach ($rootsBySection[$sectionId] ?? [] as $rootId) {
                $members = [...$members, ...$mapTask($rootId, 1, $groupId)];
            }
            $dates = array_values(array_filter(array_merge(array_column($members, 'start'), array_column($members, 'finish'))));
            $ordered[] = ['id' => $groupId, 'title' => (string) $section['name'], 'kind' => 'section', 'level' => 0, 'parent_id' => null, 'has_children' => $members !== [], 'start' => null, 'finish' => null, 'considered_start' => $dates ? min($dates) : null, 'considered_deadline' => $dates ? max($dates) : null, 'unlock_date' => null, 'completed' => false, 'planned' => $dates !== [], 'derived' => true, 'virtual_start' => null, 'sync_status' => 'synced', 'progress' => count($members) ? (int) round(count(array_filter($members, fn (array $task): bool => $task['completed'])) / count($members) * 100) : 0, 'status' => 'opened', 'critical' => false, '_member_ids' => array_column($members, 'id')];
            $ordered = [...$ordered, ...$members];
        }
        foreach ($unsectionedRoots as $rootId) {
            $ordered = [...$ordered, ...$mapTask($rootId, 0, null)];
        }

        $plans = [];
        foreach ($ordered as $item) {
            if ($item['kind'] === 'section') {
                continue;
            }
            $start = $item['start'] ? new DateTimeImmutable($item['start']) : null;
            $effectiveCompletion = $item['effective_completion'] ? new DateTimeImmutable($item['effective_completion']) : null;
            $plans[$item['id']] = TaskPlan::fromDates($item['id'], $item['title'], $start, $item['finish'] ? new DateTimeImmutable($item['finish']) : null, $calendar, $item['completed'], $effectiveCompletion, isset($plans[$item['parent_id'] ?? '']) ? $item['parent_id'] : null);
        }
        $dependencyRows = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->get();
        $domainDependencies = [];
        foreach ($dependencyRows as $dependency) {
            if (isset($plans[$dependency->predecessor_todoist_task_id], $plans[$dependency->successor_todoist_task_id])) {
                $domainDependencies[] = new Dependency($dependency->predecessor_todoist_task_id, $dependency->successor_todoist_task_id, $dependency->type);
            }
        }
        $calculation = (new SchedulingEngine($calendar))->schedule(array_values($plans), $domainDependencies, $today);
        $projectionCalculator = new TaskProjectionCalculator($calendar, $projectionPolicy);
        $projectionInputs = function (array $groupRanges = []) use ($ordered): array {
            $inputs = [];
            foreach ($ordered as $item) {
                if ($item['kind'] !== 'task') {
                    continue;
                }
                $start = isset($groupRanges[$item['id']]) ? $groupRanges[$item['id']]->start : ($item['start'] ? new DateTimeImmutable($item['start']) : null);
                $deadline = isset($groupRanges[$item['id']]) ? $groupRanges[$item['id']]->finish : ($item['finish'] ? new DateTimeImmutable($item['finish']) : null);
                $inputs[] = new TaskProjectionInput($item['id'], $start, $deadline, $item['completed'], $item['effective_completion'] ? new DateTimeImmutable($item['effective_completion']) : null);
            }

            return $inputs;
        };
        $plansFromProjections = function (array $projections) use ($ordered, $calendar): array {
            $projectedPlans = [];
            foreach ($ordered as $item) {
                if ($item['kind'] !== 'task') {
                    continue;
                }
                $projection = $projections[$item['id']];
                $projectedPlans[$item['id']] = TaskPlan::fromDates(
                    $item['id'],
                    $item['title'],
                    $projection->consideredStart,
                    $projection->consideredDeadline,
                    $calendar,
                    $item['completed'],
                    $projection->effectiveCompletionDate,
                    isset($projectedPlans[$item['parent_id'] ?? '']) ? $item['parent_id'] : null,
                );
            }

            return $projectedPlans;
        };
        $projections = $projectionCalculator->calculate($projectionInputs(), $domainDependencies, $today);
        $groups = (new GroupScheduleCalculator)->calculate($plansFromProjections($projections), $calendar);
        // A segunda passagem faz dependências de grupos usarem o intervalo derivado dos descendentes.
        $projections = $projectionCalculator->calculate($projectionInputs($groups), $domainDependencies, $today);
        $groups = (new GroupScheduleCalculator)->calculate($plansFromProjections($projections), $calendar);
        $criticalIds = array_fill_keys($calculation->criticalTaskIds, true);
        $criticalGroups = [];
        foreach ($calculation->criticalTaskIds as $id) {
            $parentId = $plans[$id]->parentId ?? null;
            while ($parentId !== null) {
                $criticalGroups[$parentId] = true;
                $parentId = $plans[$parentId]->parentId ?? null;
            }
        }
        foreach ($ordered as &$item) {
            if ($item['kind'] === 'task') {
                $projection = $projections[$item['id']];
                $item['considered_start'] = $projection->consideredStart->format('Y-m-d');
                $item['considered_deadline'] = $projection->consideredDeadline->format('Y-m-d');
                $item['unlock_date'] = $projection->unlockDate?->format('Y-m-d');
                $item['earliest_start'] = $projection->earliestStart?->format('Y-m-d');
                $item['status'] = $projection->status->value;
                $item['virtual_start'] = $item['start'] === null ? $item['considered_start'] : null;
            }
            if ($item['kind'] === 'task' && isset($groups[$item['id']])) {
                $item['considered_start'] = $groups[$item['id']]->start->format('Y-m-d');
                $item['considered_deadline'] = $groups[$item['id']]->finish->format('Y-m-d');
                $item['derived'] = true;
                $item['planned'] = true;
            }
            $item['critical'] = isset($criticalIds[$item['id']]);
            $item['total_float'] = $calculation->totalFloat[$item['id']] ?? null;
            $item['sync_status'] = $this->syncState($integration);
            if ($item['kind'] === 'section' || $item['has_children']) {
                $item['contains_critical'] = isset($criticalGroups[$item['id']]);
            }
        }
        unset($item);
        $itemsById = [];
        foreach ($ordered as $item) {
            $itemsById[$item['id']] = $item;
        }
        foreach ($ordered as &$item) {
            if ($item['kind'] !== 'section') {
                continue;
            }
            $members = array_values(array_filter(array_map(fn (string $id): ?array => $itemsById[$id] ?? null, $item['_member_ids'] ?? [])));
            $starts = array_values(array_filter(array_column($members, 'considered_start')));
            $deadlines = array_values(array_filter(array_column($members, 'considered_deadline')));
            $item['considered_start'] = $starts === [] ? null : min($starts);
            $item['considered_deadline'] = $deadlines === [] ? null : max($deadlines);
            $item['progress'] = count($members) ? (int) round(count(array_filter($members, fn (array $member): bool => $member['completed'])) / count($members) * 100) : 0;
            unset($item['_member_ids']);
        }
        unset($item);
        $leafTasks = array_values(array_filter($ordered, fn (array $task): bool => $task['kind'] === 'task' && ! $task['has_children']));
        $completed = count(array_filter($leafTasks, fn (array $task): bool => $task['completed']));
        $total = count($leafTasks);

        $dependencies = $dependencyRows->map(fn (object $dependency): array => ['id' => $dependency->id, 'from' => $dependency->predecessor_todoist_task_id, 'to' => $dependency->successor_todoist_task_id, 'type' => $dependency->type, 'critical' => (isset($criticalIds[$dependency->predecessor_todoist_task_id]) || isset($criticalGroups[$dependency->predecessor_todoist_task_id])) && isset($criticalIds[$dependency->successor_todoist_task_id])])->values()->all();

        $statusCount = fn (string $status): int => count(array_filter($leafTasks, fn (array $task): bool => $task['status'] === $status));

        return ['data' => ['project' => ['id' => $project->id, 'name' => $project->display_name, 'source' => 'Todoist', 'sync_status' => $this->syncState($integration), 'updated_at' => $integration->last_synced_at ?? now()->toIso8601String()], 'calendar' => $this->calendarPayload($project->id), 'tasks' => $ordered, 'dependencies' => $dependencies, 'stats' => ['progress' => $total ? (int) round($completed / $total * 100) : 0, 'completed' => $completed, 'total' => $total, 'critical' => count($calculation->criticalTaskIds), 'opened' => $statusCount('opened'), 'blocked' => $statusCount('blocked'), 'scheduled' => $statusCount('scheduled'), 'late' => $statusCount('late'), 'without_dates' => count(array_filter($leafTasks, fn (array $task): bool => $task['start'] === null))]], 'meta' => ['demo' => false, 'version' => (int) ($settings?->version ?? 1), 'message' => 'Dados sincronizados do Todoist.']];
    }

    private function calendarPayload(string $projectId): array
    {
        $settings = DB::table('project_settings')->where('gantt_project_id', $projectId)->first();
        $days = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7];
        $workingDays = array_values(array_filter($days, fn (int $day, string $name): bool => $settings === null ? $day <= 5 : (bool) $settings->{$name}, ARRAY_FILTER_USE_BOTH));

        return ['timezone' => 'America/Sao_Paulo', 'working_days' => $workingDays, 'rescheduling_mode' => $settings?->rescheduling_mode ?? 'MANUAL', 'projection_policy' => $settings?->projection_policy ?? 'PRESERVE_DURATION', 'exceptions' => DB::table('calendar_exceptions')->where('gantt_project_id', $projectId)->orderBy('date')->get(['date', 'type', 'description'])->map(fn (object $exception): array => ['date' => $exception->date, 'type' => $exception->type, 'description' => $exception->description])->all()];
    }

    private function syncState(object $integration): string
    {
        return $integration->sync_state === 'unknown' ? 'synced' : ($integration->sync_state ?? 'synced');
    }

    private function tasks(): array
    {
        $tasks = [
            ['id' => 'g1', 'title' => 'Estratégia & Descoberta', 'kind' => 'section', 'level' => 0, 'start' => '2026-08-17', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true],
            ['id' => 't1', 'title' => 'Mapear jornada principal', 'description' => 'Consolidar entrevistas e pontos de contato prioritários.', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-17', 'finish' => '2026-08-18', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 4, 'assignee' => 'MC'],
            ['id' => 't2', 'title' => 'Validar arquitetura técnica', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-19', 'finish' => '2026-08-20', 'progress' => 100, 'status' => 'completed', 'critical' => true, 'priority' => 3, 'assignee' => 'RL'],
            ['id' => 'g2', 'title' => 'Experiência do produto', 'kind' => 'section', 'level' => 0, 'start' => '2026-08-24', 'finish' => '2026-09-03', 'progress' => 36, 'status' => 'opened', 'critical' => true],
            ['id' => 't3', 'title' => 'Design system e fundações', 'description' => 'Tokens, componentes-base e documentação de uso.', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-24', 'finish' => '2026-08-27', 'progress' => 70, 'status' => 'scheduled', 'critical' => true, 'priority' => 4, 'assignee' => 'AS'],
            ['id' => 't4', 'title' => 'Prototipar navegação mobile', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-25', 'finish' => '2026-08-28', 'progress' => 45, 'status' => 'scheduled', 'critical' => false, 'priority' => 2, 'assignee' => 'CB'],
            ['id' => 't5', 'title' => 'Renderizador Gantt próprio', 'kind' => 'task', 'level' => 1, 'start' => '2026-08-28', 'finish' => '2026-09-03', 'progress' => 18, 'status' => 'blocked', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 'g3', 'title' => 'Integrações & Qualidade', 'kind' => 'section', 'level' => 0, 'start' => '2026-09-04', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'opened', 'critical' => true],
            ['id' => 't6', 'title' => 'OAuth e sincronização Todoist', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-08', 'progress' => 0, 'status' => 'scheduled', 'critical' => false, 'priority' => 3, 'assignee' => 'MC'],
            ['id' => 't7', 'title' => 'Golden tests do scheduling', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-04', 'finish' => '2026-09-09', 'progress' => 0, 'status' => 'blocked', 'critical' => true, 'priority' => 4, 'assignee' => 'RL'],
            ['id' => 't8', 'title' => 'Homologação do fluxo completo', 'kind' => 'task', 'level' => 1, 'start' => '2026-09-10', 'finish' => '2026-09-14', 'progress' => 0, 'status' => 'blocked', 'critical' => true, 'priority' => 3, 'assignee' => 'AS'],
            ['id' => 't9', 'title' => 'Definir campanha de lançamento', 'kind' => 'task', 'level' => 0, 'start' => null, 'finish' => null, 'progress' => 0, 'status' => 'opened', 'critical' => false, 'priority' => 1, 'assignee' => 'CB'],
        ];

        return array_map(static function (array $task): array {
            $task['completed'] = $task['status'] === 'completed';
            $task['considered_start'] = $task['start'] ?? '2026-08-20';
            $task['considered_deadline'] = $task['finish'] ?? $task['considered_start'];
            $task['unlock_date'] = null;
            $task['earliest_start'] = null;

            return $task;
        }, $tasks);
    }
}
