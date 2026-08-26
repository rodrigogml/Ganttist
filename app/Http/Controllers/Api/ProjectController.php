<?php

namespace App\Http\Controllers\Api;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\TaskProjectionCalculator;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Domain\Scheduling\WorkCalendar;
use App\Mail\ProjectInvitation;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class ProjectController
{
    public function index(Request $request): JsonResponse
    {
        $projects = DB::table('projects')
            ->join('project_members', 'project_members.project_id', '=', 'projects.id')
            ->where('project_members.user_id', $request->user()->id)
            ->orderByDesc('projects.updated_at')
            ->select(['projects.id', 'projects.name', 'projects.updated_at', 'project_members.role'])
            ->get()
            ->map(fn (object $project): array => $this->summary($project));

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'commandId' => ['required', 'string', 'max:64'],
        ]);

        $project = DB::transaction(function () use ($request, $data): object {
            $existing = DB::table('projects')->where('owner_user_id', $request->user()->id)->where('creation_command_id', $data['commandId'])->first();
            if ($existing) {
                return $existing;
            }

            $id = (string) Str::ulid();
            DB::table('projects')->insert(['id' => $id, 'owner_user_id' => $request->user()->id, 'name' => trim($data['name']), 'creation_command_id' => $data['commandId'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('project_members')->insert(['id' => (string) Str::ulid(), 'project_id' => $id, 'user_id' => $request->user()->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
            $this->ensureProjectMemberPerson($id, $request->user());

            return DB::table('projects')->where('id', $id)->first();
        });

        return response()->json(['data' => $this->summary((object) [...(array) $project, 'role' => 'owner'])], 201);
    }

    public function workspace(Request $request, string $projectId): JsonResponse
    {
        $member = $this->member($request, $projectId);
        $project = DB::table('projects')->where('id', $projectId)->firstOrFail();
        $sections = DB::table('project_sections')->where('project_id', $projectId)->orderBy('position')->get();
        $tasks = DB::table('project_tasks')->leftJoin('project_people', 'project_people.id', '=', 'project_tasks.assignee_person_id')->where('project_tasks.project_id', $projectId)->orderBy('project_tasks.position')->get(['project_tasks.*', 'project_people.name as assignee']);
        $dependencyRows = DB::table('project_task_dependencies')->where('project_id', $projectId)->get();
        $today = now('America/Sao_Paulo')->startOfDay()->toDateTimeImmutable();
        $projections = (new TaskProjectionCalculator(new WorkCalendar))->calculate(
            $tasks->map(fn (object $task): TaskProjectionInput => new TaskProjectionInput(
                $task->id,
                $task->planned_start ? new DateTimeImmutable($task->planned_start) : null,
                $task->planned_finish ? new DateTimeImmutable($task->planned_finish) : null,
                $task->completed_at !== null,
                $task->completed_at ? new DateTimeImmutable($task->completed_at) : null,
            ))->all(),
            $dependencyRows->map(fn (object $edge): Dependency => new Dependency(
                $edge->predecessor_task_id,
                $edge->successor_task_id,
                $edge->type,
            ))->all(),
            $today,
        );
        $rows = [];
        $sectionLevels = [];
        $levelFor = function (object $section) use (&$levelFor, &$sectionLevels, $sections): int {
            if (isset($sectionLevels[$section->id])) {
                return $sectionLevels[$section->id];
            }
            if (! $section->parent_section_id) {
                return $sectionLevels[$section->id] = 0;
            }
            $parent = $sections->firstWhere('id', $section->parent_section_id);

            return $sectionLevels[$section->id] = $parent ? $levelFor($parent) + 1 : 0;
        };
        $childrenByParent = collect();
        foreach ($sections as $section) {
            $childrenByParent->push((object) ['kind' => 'section', 'item' => $section, 'parent_id' => $section->parent_section_id, 'position' => $section->position]);
        }
        foreach ($tasks as $task) {
            $childrenByParent->push((object) ['kind' => 'task', 'item' => $task, 'parent_id' => $task->section_id, 'position' => $task->position]);
        }
        $childrenByParent = $childrenByParent->groupBy(fn (object $child): string => $child->parent_id ?? '__root__')->map(fn ($children) => $children->sortBy('position')->values());
        $appendChildren = null;
        $appendChildren = function (?string $parentId) use (&$appendChildren, &$rows, $childrenByParent, $sections, $tasks, $levelFor, &$sectionLevels, $projections): void {
            foreach ($childrenByParent->get($parentId ?? '__root__', collect()) as $child) {
                if ($child->kind === 'section') {
                    $section = $child->item;
                    $rows[] = ['id' => $section->id, 'title' => $section->name, 'kind' => 'section', 'parent_id' => $section->parent_section_id, 'level' => $levelFor($section), 'has_children' => $sections->contains('parent_section_id', $section->id) || $tasks->contains('section_id', $section->id), 'start' => null, 'finish' => null, 'progress' => 0, 'status' => 'opened', 'critical' => false];
                    $appendChildren($section->id);
                    continue;
                }
                $task = $child->item;
                $projection = $projections[$task->id];
                $rows[] = ['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'kind' => 'task', 'parent_id' => $task->section_id, 'section_id' => $task->section_id, 'level' => $task->section_id && isset($sectionLevels[$task->section_id]) ? $sectionLevels[$task->section_id] + 1 : 0, 'has_children' => false, 'start' => $task->planned_start, 'finish' => $task->planned_finish, 'considered_start' => $projection->consideredStart->format('Y-m-d'), 'considered_deadline' => $projection->consideredDeadline->format('Y-m-d'), 'unlock_date' => $projection->unlockDate?->format('Y-m-d'), 'earliest_start' => $projection->earliestStart?->format('Y-m-d'), 'completed' => $task->completed_at !== null, 'effective_completion' => $task->completed_at, 'progress' => $task->completed_at ? 100 : 0, 'status' => $projection->status->value, 'critical' => false, 'priority' => $task->priority, 'assignee_id' => $task->assignee_person_id, 'assignee' => $task->assignee];
            }
        };
        $appendChildren(null);
        $dependencies = $dependencyRows->map(fn (object $edge) => ['id' => $edge->id, 'from' => $edge->predecessor_task_id, 'to' => $edge->successor_task_id, 'type' => $edge->type, 'critical' => false]);
        $people = DB::table('project_people')->where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'email']);
        $leafTasks = array_values(array_filter($rows, fn (array $task): bool => $task['kind'] === 'task'));
        $completed = count(array_filter($leafTasks, fn (array $task): bool => $task['completed']));
        $statusCount = fn (string $status): int => count(array_filter($leafTasks, fn (array $task): bool => $task['status'] === $status));
        $stats = [
            'progress' => $leafTasks === [] ? 0 : (int) round($completed / count($leafTasks) * 100),
            'completed' => $completed,
            'total' => count($leafTasks),
            'critical' => 0,
            'opened' => $statusCount('opened'),
            'blocked' => $statusCount('blocked'),
            'scheduled' => $statusCount('scheduled'),
            'late' => $statusCount('late'),
            'without_dates' => count(array_filter($leafTasks, fn (array $task): bool => $task['start'] === null && $task['finish'] === null)),
        ];

        return response()->json(['data' => ['project' => ['id' => $project->id, 'name' => $project->name, 'source' => 'Local', 'sync_status' => 'local', 'updated_at' => $project->updated_at, 'role' => $member->role], 'tasks' => $rows, 'people' => $people, 'dependencies' => $dependencies, 'stats' => $stats]]);
    }

    public function createSection(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'parentSectionId' => ['nullable', 'string']]);
        $id = (string) Str::ulid();
        if (! empty($data['parentSectionId'])) {
            abort_unless(DB::table('project_sections')->where('id', $data['parentSectionId'])->where('project_id', $projectId)->exists(), 422, 'Seção-pai inválida.');
        }
        $position = $this->nextSiblingPosition($projectId, $data['parentSectionId'] ?? null);
        DB::table('project_sections')->insert(['id' => $id, 'project_id' => $projectId, 'parent_section_id' => $data['parentSectionId'] ?? null, 'name' => trim($data['name']), 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function createTask(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'sectionId' => ['nullable', 'string'], 'assigneePersonId' => ['nullable', 'string'], 'plannedStart' => ['nullable', 'date'], 'plannedFinish' => ['nullable', 'date', 'after_or_equal:plannedStart'], 'actualCompletionDate' => ['nullable', 'date']]);
        if (! empty($data['sectionId'])) {
            abort_unless(DB::table('project_sections')->where('id', $data['sectionId'])->where('project_id', $projectId)->exists(), 422, 'Seção inválida.');
        }
        if (! empty($data['assigneePersonId'])) {
            abort_unless(DB::table('project_people')->where('id', $data['assigneePersonId'])->where('project_id', $projectId)->exists(), 422, 'Responsável inválido.');
        }
        $id = (string) Str::ulid();
        $position = $this->nextSiblingPosition($projectId, $data['sectionId'] ?? null);
        DB::table('project_tasks')->insert(['id' => $id, 'project_id' => $projectId, 'section_id' => $data['sectionId'] ?? null, 'assignee_person_id' => $data['assigneePersonId'] ?? null, 'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'priority' => $data['priority'] ?? 1, 'planned_start' => $data['plannedStart'] ?? null, 'planned_finish' => $data['plannedFinish'] ?? null, 'completed_at' => $data['actualCompletionDate'] ?? null, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function updateSection(Request $request, string $projectId, string $sectionId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'parentSectionId' => ['nullable', 'string']]);
        $section = DB::table('project_sections')->where('id', $sectionId)->where('project_id', $projectId)->first();
        abort_unless($section, 404, 'Seção não encontrada.');
        abort_if(($data['parentSectionId'] ?? null) === $sectionId, 422, 'Uma seção não pode ser pai de si mesma.');
        if (! empty($data['parentSectionId'])) {
            $parent = DB::table('project_sections')->where('id', $data['parentSectionId'])->where('project_id', $projectId)->first();
            abort_unless($parent, 422, 'Seção-pai inválida.');
            $ancestor = $parent;
            while ($ancestor->parent_section_id) {
                abort_if($ancestor->parent_section_id === $sectionId, 422, 'A seção-pai criaria um ciclo.');
                $ancestor = DB::table('project_sections')->where('id', $ancestor->parent_section_id)->first();
                if (! $ancestor) {
                    break;
                }
            }
        }
        DB::table('project_sections')->where('id', $sectionId)->update(['name' => trim($data['name']), 'parent_section_id' => $data['parentSectionId'] ?? null, 'updated_at' => now()]);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $sectionId]]);
    }

    public function moveStructureItem(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate([
            'itemId' => ['required', 'string'],
            'itemKind' => ['required', 'in:task,section'],
            'parentSectionId' => ['nullable', 'string'],
            'beforeItemId' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $projectId): void {
            $table = $data['itemKind'] === 'section' ? 'project_sections' : 'project_tasks';
            $item = DB::table($table)->where('id', $data['itemId'])->where('project_id', $projectId)->first();
            abort_unless($item, 404, 'Item não encontrado.');
            $parentId = $data['parentSectionId'] ?? null;
            if ($parentId) {
                $parent = DB::table('project_sections')->where('id', $parentId)->where('project_id', $projectId)->first();
                abort_unless($parent, 422, 'Seção de destino inválida.');
                abort_if($data['itemKind'] === 'section' && $this->sectionIsDescendantOf($projectId, $parentId, $data['itemId']), 422, 'Uma seção não pode ser movida para dentro de si mesma ou de uma descendente.');
            }

            $siblings = $this->siblings($projectId, $parentId)
                ->reject(fn (array $sibling): bool => $sibling['id'] === $data['itemId'])
                ->values();
            $beforeId = $data['beforeItemId'] ?? null;
            if ($beforeId) {
                abort_if(! $siblings->contains('id', $beforeId), 422, 'A posição de destino não pertence à seção informada.');
            }
            $moving = ['id' => $data['itemId'], 'kind' => $data['itemKind']];
            $insertAt = $beforeId ? $siblings->search(fn (array $sibling): bool => $sibling['id'] === $beforeId) : $siblings->count();
            $ordered = $siblings->all();
            array_splice($ordered, $insertAt, 0, [$moving]);
            foreach ($ordered as $index => $sibling) {
                $siblingTable = $sibling['kind'] === 'section' ? 'project_sections' : 'project_tasks';
                $changes = ['position' => $index + 1, 'updated_at' => now()];
                if ($sibling['id'] === $data['itemId']) {
                    $changes[$data['itemKind'] === 'section' ? 'parent_section_id' : 'section_id'] = $parentId;
                }
                DB::table($siblingTable)->where('id', $sibling['id'])->where('project_id', $projectId)->update($changes);
            }
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
        });

        return response()->json(['data' => ['id' => $data['itemId']]]);
    }

    public function setTaskCompletion(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['completed' => ['required', 'boolean'], 'actualCompletionDate' => ['sometimes', 'nullable', 'date']]);
        $updated = DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->update(['completed_at' => $data['completed'] ? ($data['actualCompletionDate'] ?? now()->toDateString()) : null, 'updated_at' => now()]);
        abort_unless($updated, 404, 'Tarefa não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $taskId, 'completed' => $data['completed']]]);
    }

    public function updateTask(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'sectionId' => ['sometimes', 'nullable', 'string'], 'assigneePersonId' => ['sometimes', 'nullable', 'string'], 'plannedStart' => ['sometimes', 'nullable', 'date'], 'plannedFinish' => ['sometimes', 'nullable', 'date'], 'actualCompletionDate' => ['sometimes', 'nullable', 'date']]);
        if (array_key_exists('sectionId', $data) && $data['sectionId'] && ! DB::table('project_sections')->where('id', $data['sectionId'])->where('project_id', $projectId)->exists()) {
            abort(422, 'Seção inválida.');
        }
        if (array_key_exists('assigneePersonId', $data) && $data['assigneePersonId'] && ! DB::table('project_people')->where('id', $data['assigneePersonId'])->where('project_id', $projectId)->exists()) {
            abort(422, 'Responsável inválido.');
        }
        $task = DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->first();
        abort_unless($task, 404, 'Tarefa não encontrada.');
        $start = array_key_exists('plannedStart', $data) ? $data['plannedStart'] : $task->planned_start;
        $finish = array_key_exists('plannedFinish', $data) ? $data['plannedFinish'] : $task->planned_finish;
        abort_if($start && $finish && $finish < $start, 422, 'A data final não pode ser anterior à inicial.');
        $changes = ['updated_at' => now()];
        if (array_key_exists('title', $data)) {
            $changes['title'] = trim($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $changes['description'] = $data['description'];
        }
        if (array_key_exists('priority', $data)) {
            $changes['priority'] = $data['priority'];
        }
        if (array_key_exists('sectionId', $data)) {
            $changes['section_id'] = $data['sectionId'];
        }
        if (array_key_exists('assigneePersonId', $data)) {
            $changes['assignee_person_id'] = $data['assigneePersonId'];
        }
        if (array_key_exists('plannedStart', $data)) {
            $changes['planned_start'] = $data['plannedStart'];
        }
        if (array_key_exists('plannedFinish', $data)) {
            $changes['planned_finish'] = $data['plannedFinish'];
        }
        if (array_key_exists('actualCompletionDate', $data)) {
            $changes['completed_at'] = $data['actualCompletionDate'];
        }
        DB::table('project_tasks')->where('id', $taskId)->update($changes);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $taskId]]);
    }

    public function deleteTask(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $deleted = DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->delete();
        abort_unless($deleted, 404, 'Tarefa não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json([], 204);
    }

    public function duplicateTask(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $task = DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->first();
        abort_unless($task, 404, 'Tarefa não encontrada.');

        $copyId = DB::transaction(function () use ($task, $projectId): string {
            $copyId = (string) Str::ulid();
            DB::table('project_tasks')->insert([
                'id' => $copyId, 'project_id' => $projectId, 'section_id' => $task->section_id,
                'assignee_person_id' => $task->assignee_person_id, 'title' => $task->title.' - Copia',
                'description' => rtrim((string) $task->description)."\nTarefa duplicada de {$task->title}",
                'priority' => $task->priority, 'planned_start' => $task->planned_start,
                'planned_finish' => $task->planned_finish, 'completed_at' => $task->completed_at,
                'position' => $this->nextSiblingPosition($projectId, $task->section_id),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('project_task_comments')->where('task_id', $task->id)->get() as $comment) {
                DB::table('project_task_comments')->insert(['id' => (string) Str::ulid(), 'project_id' => $projectId, 'task_id' => $copyId, 'author_user_id' => $comment->author_user_id, 'content' => $comment->content, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (DB::table('project_task_dependencies')->where('project_id', $projectId)->where(function ($query) use ($task): void { $query->where('predecessor_task_id', $task->id)->orWhere('successor_task_id', $task->id); })->get() as $dependency) {
                DB::table('project_task_dependencies')->insert(['id' => (string) Str::ulid(), 'project_id' => $projectId, 'predecessor_task_id' => $dependency->predecessor_task_id === $task->id ? $copyId : $dependency->predecessor_task_id, 'successor_task_id' => $dependency->successor_task_id === $task->id ? $copyId : $dependency->successor_task_id, 'type' => $dependency->type, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
            return $copyId;
        });

        return response()->json(['data' => ['id' => $copyId]], 201);
    }

    public function taskContext(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->member($request, $projectId);
        abort_unless(DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->exists(), 404, 'Tarefa não encontrada.');
        $comments = DB::table('project_task_comments')->where('task_id', $taskId)->orderBy('created_at')->get()->map(fn (object $comment): array => ['id' => $comment->id, 'content' => $comment->content, 'author_id' => $comment->author_user_id, 'posted_at' => $comment->created_at, 'editable' => $comment->author_user_id === $request->user()->id]);

        return response()->json(['data' => ['collaborators' => DB::table('project_people')->where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'email']), 'comments' => $comments]]);
    }

    public function createComment(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['content' => ['required', 'string', 'max:10000']]);
        abort_unless(DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->exists(), 404, 'Tarefa não encontrada.');
        $id = (string) Str::ulid();
        DB::table('project_task_comments')->insert(['id' => $id, 'project_id' => $projectId, 'task_id' => $taskId, 'author_user_id' => $request->user()->id, 'content' => trim($data['content']), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function updateComment(Request $request, string $projectId, string $taskId, string $commentId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['content' => ['required', 'string', 'max:10000']]);
        $updated = DB::table('project_task_comments')->where('id', $commentId)->where('project_id', $projectId)->where('task_id', $taskId)->where('author_user_id', $request->user()->id)->update(['content' => trim($data['content']), 'updated_at' => now()]);
        abort_unless($updated, 404, 'Comentário não encontrado.');

        return response()->json(['data' => ['id' => $commentId]]);
    }

    public function deleteSection(Request $request, string $projectId, string $sectionId): JsonResponse
    {
        $this->editable($request, $projectId);
        $section = DB::table('project_sections')->where('id', $sectionId)->where('project_id', $projectId)->first();
        abort_unless($section, 404, 'Seção não encontrada.');
        $data = $request->validate(['action' => ['sometimes', 'in:delete,move'], 'destinationSectionId' => ['nullable', 'string']]);
        if (($data['action'] ?? 'delete') === 'move') {
            $destination = $data['destinationSectionId'] ?? null;
            abort_if($destination === $sectionId || ($destination && $this->sectionIsDescendantOf($projectId, $destination, $sectionId)), 422, 'O destino não pode estar dentro da seção removida.');
            if ($destination) abort_unless(DB::table('project_sections')->where('id', $destination)->where('project_id', $projectId)->exists(), 422, 'Seção de destino inválida.');
            DB::transaction(function () use ($projectId, $sectionId, $destination): void {
                DB::table('project_sections')->where('project_id', $projectId)->where('parent_section_id', $sectionId)->update(['parent_section_id' => $destination, 'updated_at' => now()]);
                DB::table('project_tasks')->where('project_id', $projectId)->where('section_id', $sectionId)->update(['section_id' => $destination, 'updated_at' => now()]);
                DB::table('project_sections')->where('id', $sectionId)->delete();
            });
        } else {
            DB::table('project_sections')->where('id', $sectionId)->delete();
        }
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json([], 204);
    }

    public function createDependency(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['from' => ['required', 'string'], 'to' => ['required', 'string', 'different:from'], 'type' => ['required', 'in:FS,SS,FF,SF']]);
        abort_unless(DB::table('project_tasks')->where('project_id', $projectId)->whereIn('id', [$data['from'], $data['to']])->count() === 2, 422, 'Tarefas inválidas.');
        abort_if(DB::table('project_task_dependencies')->where('project_id', $projectId)->where('predecessor_task_id', $data['from'])->where('successor_task_id', $data['to'])->where('type', $data['type'])->exists(), 422, 'Dependência duplicada.');
        abort_if($this->wouldCycle($projectId, $data['from'], $data['to']), 422, 'Dependência criaria um ciclo.');
        $id = (string) Str::ulid();
        DB::table('project_task_dependencies')->insert(['id' => $id, 'project_id' => $projectId, 'predecessor_task_id' => $data['from'], 'successor_task_id' => $data['to'], 'type' => $data['type'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function deleteDependency(Request $request, string $projectId, string $dependencyId): JsonResponse
    {
        $this->editable($request, $projectId);
        $deleted = DB::table('project_task_dependencies')->where('id', $dependencyId)->where('project_id', $projectId)->delete();
        abort_unless($deleted, 404, 'Dependência não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json([], 204);
    }

    public function createPerson(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255']]);
        $id = (string) Str::ulid();
        DB::table('project_people')->insert(['id' => $id, 'project_id' => $projectId, 'name' => trim($data['name']), 'email' => $data['email'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'name' => trim($data['name']), 'email' => $data['email'] ?? null]], 201);
    }

    public function updatePerson(Request $request, string $projectId, string $personId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255']]);
        $updated = DB::table('project_people')->where('id', $personId)->where('project_id', $projectId)->update(['name' => trim($data['name']), 'email' => $data['email'] ?? null, 'updated_at' => now()]);
        abort_unless($updated, 404, 'Pessoa não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $personId, 'name' => trim($data['name']), 'email' => $data['email'] ?? null]]);
    }

    public function deletePerson(Request $request, string $projectId, string $personId): JsonResponse
    {
        $this->editable($request, $projectId);
        $deleted = DB::table('project_people')->where('id', $personId)->where('project_id', $projectId)->delete();
        abort_unless($deleted, 404, 'Pessoa não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json([], 204);
    }

    public function inviteMember(Request $request, string $projectId): JsonResponse
    {
        $member = $this->member($request, $projectId);
        abort_unless($member->role === 'owner', 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', 'in:editor,reader']]);
        $project = DB::table('projects')->where('id', $projectId)->firstOrFail();
        $id = (string) Str::ulid();
        $expiresAt = now()->addDays(7);
        Mail::to(strtolower($data['email']))->send(new ProjectInvitation($project->name, $data['role'], $expiresAt));
        DB::table('project_invitations')->insert(['id' => $id, 'project_id' => $projectId, 'invited_by_user_id' => $request->user()->id, 'email' => strtolower($data['email']), 'role' => $data['role'], 'status' => 'pending', 'token_hash' => hash('sha256', Str::random(64)), 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'pending']], 201);
    }

    public function members(Request $request, string $projectId): JsonResponse
    {
        $this->member($request, $projectId);
        $members = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_members.project_id', $projectId)->orderBy('project_members.created_at')->get(['project_members.id', 'project_members.user_id', 'project_members.role', 'users.name', 'users.email'])->map(fn (object $member): array => (array) $member);
        $invitations = DB::table('project_invitations')->where('project_id', $projectId)->where('status', 'pending')->orderByDesc('created_at')->get(['id', 'email', 'role', 'status', 'expires_at'])->map(fn (object $invitation): array => (array) $invitation);
        $people = DB::table('project_people')->where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'email'])->map(fn (object $person): array => (array) $person);

        return response()->json(['data' => ['members' => $members, 'invitations' => $invitations, 'people' => $people]]);
    }

    public function updateMember(Request $request, string $projectId, string $memberId): JsonResponse
    {
        $member = $this->member($request, $projectId);
        abort_unless($member->role === 'owner', 403);
        $data = $request->validate(['role' => ['required', 'in:editor,reader']]);
        $updated = DB::table('project_members')->where('id', $memberId)->where('project_id', $projectId)->where('role', '!=', 'owner')->update(['role' => $data['role'], 'updated_at' => now()]);
        abort_unless($updated, 404, 'Membro não encontrado.');

        return response()->json(['data' => ['id' => $memberId, 'role' => $data['role']]]);
    }

    public function removeMember(Request $request, string $projectId, string $memberId): JsonResponse
    {
        $member = $this->member($request, $projectId);
        abort_unless($member->role === 'owner', 403);
        $deleted = DB::table('project_members')->where('id', $memberId)->where('project_id', $projectId)->where('role', '!=', 'owner')->delete();
        abort_unless($deleted, 404, 'Membro não encontrado.');

        return response()->json([], 204);
    }

    public function deleteProject(Request $request, string $projectId): JsonResponse
    {
        $member = $this->member($request, $projectId);
        abort_unless($member->role === 'owner', 403);
        DB::table('projects')->where('id', $projectId)->delete();

        return response()->json([], 204);
    }

    public function acceptInvitation(Request $request, string $invitationId): JsonResponse
    {
        $invitation = DB::table('project_invitations')->where('id', $invitationId)->where('status', 'pending')->first();
        abort_unless($invitation && strtolower($request->user()->email) === $invitation->email && (! $invitation->expires_at || $invitation->expires_at >= now()), 404);
        DB::transaction(function () use ($invitation, $request): void {
            $memberQuery = DB::table('project_members')->where('project_id', $invitation->project_id)->where('user_id', $request->user()->id);
            if ($memberQuery->exists()) {
                $memberQuery->update(['role' => $invitation->role, 'updated_at' => now()]);
            } else {
                DB::table('project_members')->insert(['id' => (string) Str::ulid(), 'project_id' => $invitation->project_id, 'user_id' => $request->user()->id, 'role' => $invitation->role, 'created_at' => now(), 'updated_at' => now()]);
            }
            $person = DB::table('project_people')
                ->where('project_id', $invitation->project_id)
                ->whereNull('linked_user_id')
                ->where('email', $request->user()->email)
                ->first();
            if ($person) {
                DB::table('project_people')->where('id', $person->id)->update(['linked_user_id' => $request->user()->id, 'updated_at' => now()]);
            } else {
                $this->ensureProjectMemberPerson($invitation->project_id, $request->user());
            }
            DB::table('project_invitations')->where('id', $invitation->id)->update(['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => ['projectId' => $invitation->project_id, 'role' => $invitation->role]]);
    }

    public function pendingInvitations(Request $request): JsonResponse
    {
        $invitations = DB::table('project_invitations')->join('projects', 'projects.id', '=', 'project_invitations.project_id')->where('project_invitations.status', 'pending')->where('project_invitations.email', strtolower($request->user()->email))->where(fn ($query) => $query->whereNull('project_invitations.expires_at')->orWhere('project_invitations.expires_at', '>=', now()))->orderByDesc('project_invitations.created_at')->get(['project_invitations.id', 'project_invitations.role', 'project_invitations.expires_at', 'projects.id as project_id', 'projects.name as project_name'])->map(fn (object $invitation): array => (array) $invitation);

        return response()->json(['data' => $invitations]);
    }

    private function member(Request $request, string $projectId): object
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $request->user()->id)->first() ?? abort(404);
    }

    private function editable(Request $request, string $projectId): object
    {
        $member = $this->member($request, $projectId);
        abort_unless(in_array($member->role, ['owner', 'editor'], true), 403);

        return $member;
    }

    private function ensureProjectMemberPerson(string $projectId, object $user): void
    {
        $exists = DB::table('project_people')
            ->where('project_id', $projectId)
            ->where('linked_user_id', $user->id)
            ->exists();

        if (! $exists) {
            DB::table('project_people')->insert([
                'id' => (string) Str::ulid(),
                'project_id' => $projectId,
                'linked_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function status(object $task, array $incompletePredecessors): string
    {
        if ($task->completed_at) {
            return 'completed';
        }
        if ($task->planned_finish && $task->planned_finish < now()->toDateString()) {
            return 'late';
        }
        if (in_array($task->id, $incompletePredecessors, true)) {
            return 'blocked';
        }

        return $task->planned_start && $task->planned_start > now()->toDateString() ? 'scheduled' : 'opened';
    }

    private function wouldCycle(string $projectId, string $from, string $to): bool
    {
        $edges = DB::table('project_task_dependencies')->where('project_id', $projectId)->get(['predecessor_task_id', 'successor_task_id']);
        $graph = [];
        foreach ($edges as $edge) {
            $graph[$edge->successor_task_id][] = $edge->predecessor_task_id;
        }
        $stack = [$from];
        $seen = [];
        while ($stack) {
            $node = array_pop($stack);
            if ($node === $to) {
                return true;
            } if (isset($seen[$node])) {
                continue;
            } $seen[$node] = true;
            foreach ($graph[$node] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }

    private function nextSiblingPosition(string $projectId, ?string $parentSectionId): int
    {
        return $this->siblings($projectId, $parentSectionId)->count() + 1;
    }

    private function siblings(string $projectId, ?string $parentSectionId): \Illuminate\Support\Collection
    {
        $sections = DB::table('project_sections')->where('project_id', $projectId);
        $tasks = DB::table('project_tasks')->where('project_id', $projectId);
        if ($parentSectionId) {
            $sections->where('parent_section_id', $parentSectionId);
            $tasks->where('section_id', $parentSectionId);
        } else {
            $sections->whereNull('parent_section_id');
            $tasks->whereNull('section_id');
        }

        return $sections->get(['id', 'position'])->map(fn (object $item): array => ['id' => $item->id, 'kind' => 'section', 'position' => $item->position])
            ->concat($tasks->get(['id', 'position'])->map(fn (object $item): array => ['id' => $item->id, 'kind' => 'task', 'position' => $item->position]))
            ->sortBy('position')
            ->values();
    }

    private function sectionIsDescendantOf(string $projectId, string $sectionId, string $ancestorId): bool
    {
        $currentId = $sectionId;
        while ($currentId) {
            if ($currentId === $ancestorId) {
                return true;
            }
            $currentId = DB::table('project_sections')->where('id', $currentId)->where('project_id', $projectId)->value('parent_section_id');
        }

        return false;
    }

    private function summary(object $project): array
    {
        $tasks = DB::table('project_tasks')->where('project_id', $project->id)->get(['planned_start', 'planned_finish', 'completed_at']);
        $totalWeight = 0;
        $completedWeight = 0;
        $overdue = 0;
        foreach ($tasks as $task) {
            $weight = $task->planned_start && $task->planned_finish ? max(1, (new \DateTimeImmutable($task->planned_start))->diff(new \DateTimeImmutable($task->planned_finish))->days + 1) : 1;
            $totalWeight += $weight;
            $completedWeight += $task->completed_at ? $weight : 0;
            $overdue += ! $task->completed_at && $task->planned_finish && $task->planned_finish < now()->toDateString() ? 1 : 0;
        }

        $statusCounts = ['opened' => 0, 'blocked' => 0, 'scheduled' => 0, 'late' => 0];
        $incompletePredecessors = DB::table('project_task_dependencies')->join('project_tasks as predecessor', 'predecessor.id', '=', 'project_task_dependencies.predecessor_task_id')->where('project_task_dependencies.project_id', $project->id)->whereNull('predecessor.completed_at')->pluck('project_task_dependencies.successor_task_id')->all();
        foreach (DB::table('project_tasks')->where('project_id', $project->id)->get() as $task) {
            $status = $this->status($task, $incompletePredecessors);
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        return ['id' => $project->id, 'name' => $project->name, 'taskCount' => $tasks->count(), 'progress' => $totalWeight ? (int) round($completedWeight / $totalWeight * 100) : 0, 'overdueTaskCount' => $overdue, 'role' => $project->role, 'updatedAt' => $project->updated_at, 'completed' => $tasks->whereNotNull('completed_at')->count(), 'total' => $tasks->count(), 'critical' => 0, 'opened' => $statusCounts['opened'], 'blocked' => $statusCounts['blocked'], 'scheduled' => $statusCounts['scheduled'], 'late' => $statusCounts['late'], 'without_dates' => $tasks->filter(fn (object $task): bool => ! $task->planned_start && ! $task->planned_finish)->count()];
    }
}
