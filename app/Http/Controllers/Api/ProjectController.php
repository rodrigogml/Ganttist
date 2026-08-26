<?php

namespace App\Http\Controllers\Api;

use App\Mail\ProjectInvitation;
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
        foreach ($sections as $section) {
            $rows[] = ['id' => $section->id, 'title' => $section->name, 'kind' => 'section', 'parent_id' => $section->parent_section_id, 'level' => $levelFor($section), 'has_children' => $sections->contains('parent_section_id', $section->id) || $tasks->contains('section_id', $section->id), 'start' => null, 'finish' => null, 'progress' => 0, 'status' => 'opened', 'critical' => false];
        }
        $incompletePredecessors = DB::table('project_task_dependencies')->join('project_tasks as predecessor', 'predecessor.id', '=', 'project_task_dependencies.predecessor_task_id')->where('project_task_dependencies.project_id', $projectId)->whereNull('predecessor.completed_at')->pluck('project_task_dependencies.successor_task_id')->all();
        foreach ($tasks as $task) {
            $rows[] = ['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'kind' => 'task', 'parent_id' => $task->section_id, 'level' => $task->section_id && isset($sectionLevels[$task->section_id]) ? $sectionLevels[$task->section_id] + 1 : 0, 'has_children' => false, 'start' => $task->planned_start, 'finish' => $task->planned_finish, 'completed' => $task->completed_at !== null, 'effective_completion' => $task->completed_at, 'progress' => $task->completed_at ? 100 : 0, 'status' => $this->status($task, $incompletePredecessors), 'critical' => false, 'assignee_id' => $task->assignee_person_id, 'assignee' => $task->assignee];
        }
        $dependencies = DB::table('project_task_dependencies')->where('project_id', $projectId)->get()->map(fn (object $edge) => ['id' => $edge->id, 'from' => $edge->predecessor_task_id, 'to' => $edge->successor_task_id, 'type' => $edge->type, 'critical' => false]);
        $people = DB::table('project_people')->where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json(['data' => ['project' => ['id' => $project->id, 'name' => $project->name, 'source' => 'Local', 'sync_status' => 'local', 'updated_at' => $project->updated_at, 'role' => $member->role], 'tasks' => $rows, 'people' => $people, 'dependencies' => $dependencies, 'stats' => $this->summary((object) [...(array) $project, 'role' => $member->role])]]);
    }

    public function createSection(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'parentSectionId' => ['nullable', 'string']]);
        $id = (string) Str::ulid();
        if (! empty($data['parentSectionId'])) {
            abort_unless(DB::table('project_sections')->where('id', $data['parentSectionId'])->where('project_id', $projectId)->exists(), 422, 'Seção-pai inválida.');
        }
        DB::table('project_sections')->insert(['id' => $id, 'project_id' => $projectId, 'parent_section_id' => $data['parentSectionId'] ?? null, 'name' => trim($data['name']), 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function createTask(Request $request, string $projectId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'sectionId' => ['nullable', 'string'], 'assigneePersonId' => ['nullable', 'string'], 'plannedStart' => ['nullable', 'date'], 'plannedFinish' => ['nullable', 'date', 'after_or_equal:plannedStart'], 'actualCompletionDate' => ['nullable', 'date']]);
        if (! empty($data['sectionId'])) {
            abort_unless(DB::table('project_sections')->where('id', $data['sectionId'])->where('project_id', $projectId)->exists(), 422, 'Seção inválida.');
        }
        if (! empty($data['assigneePersonId'])) {
            abort_unless(DB::table('project_people')->where('id', $data['assigneePersonId'])->where('project_id', $projectId)->exists(), 422, 'Responsável inválido.');
        }
        $id = (string) Str::ulid();
        DB::table('project_tasks')->insert(['id' => $id, 'project_id' => $projectId, 'section_id' => $data['sectionId'] ?? null, 'assignee_person_id' => $data['assigneePersonId'] ?? null, 'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'planned_start' => $data['plannedStart'] ?? null, 'planned_finish' => $data['plannedFinish'] ?? null, 'completed_at' => $data['actualCompletionDate'] ?? null, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function setTaskCompletion(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $updated = DB::table('project_tasks')->where('id', $taskId)->where('project_id', $projectId)->update(['completed_at' => $data['completed'] ? now()->toDateString() : null, 'updated_at' => now()]);
        abort_unless($updated, 404, 'Tarefa não encontrada.');
        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);

        return response()->json(['data' => ['id' => $taskId, 'completed' => $data['completed']]]);
    }

    public function updateTask(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $this->editable($request, $projectId);
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'sectionId' => ['sometimes', 'nullable', 'string'], 'assigneePersonId' => ['sometimes', 'nullable', 'string'], 'plannedStart' => ['sometimes', 'nullable', 'date'], 'plannedFinish' => ['sometimes', 'nullable', 'date'], 'actualCompletionDate' => ['sometimes', 'nullable', 'date']]);
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
        DB::table('project_sections')->where('id', $sectionId)->delete();
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
