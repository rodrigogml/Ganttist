<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use App\Services\AuditWriter;
use App\Services\TodoistAccessTokenService;
use App\Services\TodoistSnapshotStore;
use App\Support\TodoistTask;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TaskController extends Controller
{
    public function __construct(private readonly TodoistAccessTokenService $tokens) {}

    public function store(Request $request, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:500'], 'parentId' => ['nullable', 'string', 'max:255'], 'commandId' => ['required', 'string', 'max:64']]);
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        if ($data['parentId'] ?? null) {
            abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $data['parentId']), 422, 'A tarefa pai não pertence ao projeto selecionado.');
        }
        $task = $gateway->createTask($this->tokens->accessToken($integration), array_filter(['content' => $data['title'], 'project_id' => $project->todoist_project_id, 'parent_id' => $data['parentId'] ?? null]));
        $audit->record($request->user()->id, $project->id, 'task.created', 'user', 'todoist_task', (string) ($task['id'] ?? ''), $data['commandId'], null, ['parent_id' => $data['parentId'] ?? null]);

        return response()->json(['data' => $task], 201);
    }

    public function deletionPreview(Request $request, string $taskId, TodoistGateway $gateway): JsonResponse
    {
        [$project, , $tasks] = $this->authorizedTasks($request, $gateway);
        abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId), 404, 'Tarefa não pertence ao projeto selecionado.');
        $incoming = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('successor_todoist_task_id', $taskId)->where('status', 'active')->get();
        $outgoing = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('predecessor_todoist_task_id', $taskId)->where('status', 'active')->get();
        $continuity = [];
        foreach ($incoming as $from) {
            foreach ($outgoing as $to) {
                if ($from->predecessor_todoist_task_id !== $to->successor_todoist_task_id) {
                    $continuity[] = ['from' => $from->predecessor_todoist_task_id, 'to' => $to->successor_todoist_task_id, 'type' => 'FS'];
                }
            }
        }

        return response()->json(['data' => ['task_id' => $taskId, 'incoming' => $incoming->map(fn ($edge) => ['id' => $edge->id, 'from' => $edge->predecessor_todoist_task_id, 'type' => $edge->type]), 'outgoing' => $outgoing->map(fn ($edge) => ['id' => $edge->id, 'to' => $edge->successor_todoist_task_id, 'type' => $edge->type]), 'continuity' => $continuity]]);
    }

    public function destroy(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'preserveContinuity' => ['required', 'boolean'], 'commandId' => ['required', 'string', 'max:64']]);
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId), 404, 'Tarefa não pertence ao projeto selecionado.');
        abort_if(collect($tasks)->contains(fn (array $task): bool => (string) ($task['parent_id'] ?? '') === $taskId), 422, 'Exclua ou mova as subtarefas antes de remover um grupo.');
        $incoming = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('successor_todoist_task_id', $taskId)->where('status', 'active')->get();
        $outgoing = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('predecessor_todoist_task_id', $taskId)->where('status', 'active')->get();
        $gateway->deleteTask($this->tokens->accessToken($integration), $taskId);
        DB::transaction(function () use ($project, $taskId, $incoming, $outgoing, $data): void {
            DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where(fn ($query) => $query->where('predecessor_todoist_task_id', $taskId)->orWhere('successor_todoist_task_id', $taskId))->where('status', 'active')->update(['status' => 'removed', 'updated_at' => now()]);
            if ($data['preserveContinuity']) {
                foreach ($incoming as $from) {
                    foreach ($outgoing as $to) {
                        if ($from->predecessor_todoist_task_id === $to->successor_todoist_task_id) {
                            continue;
                        }
                        $exists = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('predecessor_todoist_task_id', $from->predecessor_todoist_task_id)->where('successor_todoist_task_id', $to->successor_todoist_task_id)->where('status', 'active')->exists();
                        if (! $exists) {
                            DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $project->id, 'predecessor_todoist_task_id' => $from->predecessor_todoist_task_id, 'successor_todoist_task_id' => $to->successor_todoist_task_id, 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
            }
        });
        $audit->record($request->user()->id, $project->id, 'task.deleted', 'user', 'todoist_task', $taskId, $data['commandId'], ['incoming' => $incoming->count(), 'outgoing' => $outgoing->count()], ['continuity_preserved' => $data['preserveContinuity']]);

        return response()->json(['data' => ['deleted_task_id' => $taskId, 'continuity_preserved' => $data['preserveContinuity']]]);
    }

    public function update(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit, TodoistSnapshotStore $snapshots): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:500'], 'description' => ['sometimes', 'nullable', 'string', 'max:15000'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'assigneeId' => ['sometimes', 'nullable', 'string', 'max:255'], 'completed' => ['sometimes', 'boolean'], 'commandId' => ['required', 'string', 'max:64'], 'start' => ['prohibited'], 'finish' => ['prohibited']]);
        [$project, $integration, $tasks, $snapshot] = $this->authorizedTasks($request, $gateway, true);
        $source = collect($tasks)->first(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId);
        abort_unless($source, 404, 'Tarefa não pertence ao projeto selecionado.');
        $collaborators = $snapshot['collaborators']['results'] ?? [];
        if (($data['assigneeId'] ?? null) !== null) {
            abort_unless(collect($collaborators)->contains(fn (array $collaborator): bool => (string) ($collaborator['id'] ?? $collaborator['user_id'] ?? '') === $data['assigneeId']), 422, 'O responsável não pertence ao projeto selecionado.');
        }
        $attributes = ['content' => $data['title']];
        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'] ?? '';
        }
        if (array_key_exists('priority', $data)) {
            $attributes['priority'] = $data['priority'];
        }
        if (array_key_exists('assigneeId', $data)) {
            $attributes['assignee_id'] = $data['assigneeId'];
        }
        $task = $gateway->updateTask($this->tokens->accessToken($integration), $taskId, $attributes);
        if (array_key_exists('completed', $data)) {
            $gateway->setTaskCompletion($this->tokens->accessToken($integration), $taskId, $data['completed']);
        }
        $snapshots->forget($project->id);
        $audit->record($request->user()->id, $project->id, 'task.updated', 'user', 'todoist_task', $taskId, $data['commandId'], ['title' => $source['content'] ?? null, 'description' => $source['description'] ?? null, 'priority' => $source['priority'] ?? null, 'assignee_id' => $source['assignee_id'] ?? $source['responsible_uid'] ?? null, 'completed' => $source['is_completed'] ?? null], ['title' => $data['title'], 'description' => $data['description'] ?? $source['description'] ?? null, 'priority' => $data['priority'] ?? $source['priority'] ?? null, 'assignee_id' => $data['assigneeId'] ?? null, 'completed' => $data['completed'] ?? $source['is_completed'] ?? null]);

        return response()->json(['data' => $task]);
    }

    public function setCompletion(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit, TodoistSnapshotStore $snapshots): JsonResponse
    {
        $data = $request->validate(['completed' => ['required', 'boolean'], 'commandId' => ['required', 'string', 'max:64']]);
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');
        $snapshot = $snapshots->get($project->id);
        abort_unless($snapshot, 409, 'Atualize o workspace antes de alterar a conclusão da tarefa.');
        $tasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
        $index = collect($tasks)->search(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId);
        abort_if($index === false, 404, 'Tarefa não pertence ao projeto selecionado.');
        $source = $tasks[$index];
        $completed = (bool) $data['completed'];
        if (TodoistTask::completed($source) === $completed) {
            return response()->json(['data' => ['task_id' => $taskId, 'completed' => $completed, 'unchanged' => true]]);
        }
        try {
            $gateway->setTaskCompletion($this->tokens->accessToken($integration), $taskId, $completed);
        } catch (RequestException $exception) {
            $retryAfter = data_get($exception->response->json(), 'error_extra.retry_after');
            Log::warning('task.completion.todoist_failed', [
                'project_id' => $project->id,
                'task_hash' => substr(hash('sha256', $taskId), 0, 12),
                'status' => $exception->response->status(),
                'retry_after' => is_numeric($retryAfter) ? (int) $retryAfter : null,
                'error_tag' => data_get($exception->response->json(), 'error_tag'),
                'error_code' => data_get($exception->response->json(), 'error_code'),
            ]);

            return response()->json(['message' => is_numeric($retryAfter)
                ? "O Todoist pediu para tentar novamente em {$retryAfter} segundos."
                : 'O Todoist não confirmou a alteração da tarefa. Tente novamente.'], 503);
        }
        $tasks[$index]['is_completed'] = $completed;
        $tasks[$index]['checked'] = $completed;
        if ($completed) {
            $tasks[$index]['completed_at'] = now('UTC')->toIso8601String();
        } else {
            unset($tasks[$index]['completed_at']);
        }
        if (isset($snapshot['tasks']['results'])) {
            $snapshot['tasks']['results'] = $tasks;
        } else {
            $snapshot['tasks'] = $tasks;
        }
        $snapshots->put($project->id, $snapshot);
        $audit->record($request->user()->id, $project->id, 'task.completion_updated', 'user', 'todoist_task', $taskId, $data['commandId'], ['completed' => TodoistTask::completed($source)], ['completed' => $completed]);

        return response()->json(['data' => ['task_id' => $taskId, 'completed' => $completed, 'unchanged' => false]]);
    }

    public function editorContext(Request $request, string $taskId, TodoistGateway $gateway): JsonResponse
    {
        [$project, $integration, $tasks, $snapshot] = $this->authorizedTasks($request, $gateway, true);
        abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId), 404, 'Tarefa não pertence ao projeto selecionado.');
        $comments = $gateway->comments($this->tokens->accessToken($integration), $taskId)['results'] ?? [];
        $collaborators = $snapshot['collaborators']['results'] ?? [];

        return response()->json(['data' => [
            'collaborators' => collect($collaborators)->map(fn (array $item): array => ['id' => (string) ($item['id'] ?? $item['user_id'] ?? ''), 'name' => (string) ($item['name'] ?? $item['full_name'] ?? $item['email'] ?? 'Sem nome'), 'email' => $item['email'] ?? null])->values(),
            'comments' => collect($comments)->map(fn (array $item): array => ['id' => (string) $item['id'], 'content' => (string) ($item['content'] ?? ''), 'author_id' => (string) ($item['posted_uid'] ?? ''), 'posted_at' => $item['posted_at'] ?? $item['posted'] ?? null, 'editable' => (string) ($item['posted_uid'] ?? '') === (string) ($integration->todoist_user_id ?? '')])->values(),
        ]]);
    }

    public function createComment(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:15000'], 'commandId' => ['required', 'string', 'max:64']]);
        abort_if(trim($data['content']) === '', 422, 'Informe o conteúdo do comentário.');
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId), 404, 'Tarefa não pertence ao projeto selecionado.');
        $comment = $gateway->createComment($this->tokens->accessToken($integration), $taskId, trim($data['content']));
        $audit->record($request->user()->id, $project->id, 'task.comment_created', 'user', 'todoist_comment', (string) ($comment['id'] ?? ''), $data['commandId'], null, ['task_id' => $taskId]);

        return response()->json(['data' => $comment], 201);
    }

    public function updateComment(Request $request, string $taskId, string $commentId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:15000'], 'commandId' => ['required', 'string', 'max:64']]);
        abort_if(trim($data['content']) === '', 422, 'Informe o conteúdo do comentário.');
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        abort_unless(collect($tasks)->contains(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId), 404, 'Tarefa não pertence ao projeto selecionado.');
        $comments = $gateway->comments($this->tokens->accessToken($integration), $taskId)['results'] ?? [];
        $source = collect($comments)->first(fn (array $comment): bool => (string) ($comment['id'] ?? '') === $commentId);
        abort_unless($source, 404, 'Comentário não pertence à tarefa selecionada.');
        abort_unless((string) ($source['posted_uid'] ?? '') === (string) ($integration->todoist_user_id ?? ''), 403, 'Somente o autor pode editar este comentário.');
        $comment = $gateway->updateComment($this->tokens->accessToken($integration), $commentId, trim($data['content']));
        $audit->record($request->user()->id, $project->id, 'task.comment_updated', 'user', 'todoist_comment', $commentId, $data['commandId'], null, ['task_id' => $taskId]);

        return response()->json(['data' => $comment]);
    }

    public function updateDates(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit, TodoistSnapshotStore $snapshots): JsonResponse
    {
        $data = $request->validate([
            'intent' => ['required', 'in:MOVE,RESIZE_START,RESIZE_END'],
            'start' => ['sometimes', 'date_format:Y-m-d'],
            'deadline' => ['sometimes', 'date_format:Y-m-d'],
            'commandId' => ['required', 'string', 'max:64'],
            'finish' => ['prohibited'],
        ]);
        abort_if(in_array($data['intent'], ['MOVE', 'RESIZE_START'], true) && ! isset($data['start']), 422, 'Informe a nova data inicial.');
        abort_if(in_array($data['intent'], ['RESIZE_START', 'RESIZE_END'], true) && ! isset($data['deadline']), 422, 'Informe a nova deadline.');
        abort_if($data['intent'] === 'MOVE' && isset($data['deadline']), 422, 'Movimento não aceita deadline explícita.');
        abort_if($data['intent'] === 'RESIZE_END' && isset($data['start']), 422, 'Resize final altera somente a deadline.');
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        $source = collect($tasks)->first(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId);
        abort_unless($source, 404, 'Tarefa não pertence ao projeto selecionado.');
        if ($data['intent'] !== 'MOVE') {
            abort_if(TodoistTask::completed($source), 422, 'Tarefas concluídas não podem ser redimensionadas.');
            abort_if(collect($tasks)->contains(fn (array $task): bool => (string) ($task['parent_id'] ?? '') === $taskId), 422, 'Grupos derivados não podem ser redimensionados diretamente.');
        }

        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $sourceStart = TodoistTask::start($source);
        $referenceStart = Carbon::createFromFormat('Y-m-d', $sourceStart ?? now($timezone)->toDateString(), $timezone)->startOfDay();
        $sourceDeadline = TodoistTask::deadline($source);
        if ($data['intent'] === 'MOVE') {
            $targetStart = Carbon::createFromFormat('Y-m-d', $data['start'], $timezone)->startOfDay();
            $dayDelta = (int) $referenceStart->diffInDays($targetStart, false);
            $targetDeadline = $sourceDeadline
                ? Carbon::createFromFormat('Y-m-d', $sourceDeadline, $timezone)->addDays($dayDelta)->toDateString()
                : null;
        } elseif ($data['intent'] === 'RESIZE_START') {
            $targetStart = Carbon::createFromFormat('Y-m-d', $data['start'], $timezone)->startOfDay();
            $targetDeadline = $data['deadline'];
        } else {
            $targetStart = $referenceStart;
            $targetDeadline = $data['deadline'];
        }
        abort_if($targetDeadline !== null && $targetStart->toDateString() > $targetDeadline, 422, 'A deadline não pode ser anterior à data inicial.');

        $gateway->updateTaskDates($this->tokens->accessToken($integration), $taskId, $targetStart->toDateString(), $targetDeadline);
        // The next workspace read must reconcile against Todoist instead of serving the pre-mutation cache.
        $snapshots->forget($project->id);
        $before = ['start' => $sourceStart, 'deadline' => $sourceDeadline];
        $after = ['start' => $targetStart->toDateString(), 'deadline' => $targetDeadline, 'intent' => $data['intent']];
        $audit->record($request->user()->id, $project->id, 'task.dates_updated', 'user', 'todoist_task', $taskId, $data['commandId'], $before, $after);

        return response()->json(['data' => [
            'task_id' => $taskId,
            'intent' => $data['intent'],
            'start' => $targetStart->toDateString(),
            'finish' => $targetDeadline,
            'deadline' => $targetDeadline,
        ]]);
    }

    public function updateCompletionDate(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['completionDate' => ['required', 'date_format:Y-m-d'], 'commandId' => ['required', 'string', 'max:64']]);
        [$project, , $tasks] = $this->authorizedTasks($request, $gateway);
        $task = collect($tasks)->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $taskId);
        abort_unless($task, 404, 'Tarefa não pertence ao projeto selecionado.');
        abort_unless((bool) ($task['is_completed'] ?? false), 422, 'A data efetiva só pode ser corrigida em tarefa concluída.');
        $metadata = DB::table('task_metadata')->where('gantt_project_id', $project->id)->where('todoist_task_id', $taskId)->first();
        $before = $metadata?->completion_date_override;
        if ($metadata) {
            DB::table('task_metadata')->where('id', $metadata->id)->update(['completion_date_override' => $data['completionDate'], 'status' => 'active', 'updated_at' => now()]);
        } else {
            DB::table('task_metadata')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $project->id, 'todoist_task_id' => $taskId, 'completion_date_override' => $data['completionDate'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $audit->record($request->user()->id, $project->id, 'task.completion_date_overridden', 'user', 'todoist_task', $taskId, $data['commandId'], ['completion_date' => $before], ['completion_date' => $data['completionDate']]);

        return response()->json(['data' => ['task_id' => $taskId, 'completion_date' => $data['completionDate'], 'overridden' => true]]);
    }

    /** @return array{object, object, array<int, array<string, mixed>>} */
    private function authorizedTasks(Request $request, TodoistGateway $gateway, bool $includeSnapshot = false): array
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');
        $snapshot = $gateway->projectSnapshot($this->tokens->accessToken($integration), $project->todoist_project_id);

        $result = [$project, $integration, $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? []];

        return $includeSnapshot ? [...$result, $snapshot] : $result;
    }
}
