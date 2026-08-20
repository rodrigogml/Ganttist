<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use App\Services\AuditWriter;
use App\Services\TodoistAccessTokenService;
use App\Support\TodoistTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function update(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:500'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'completed' => ['sometimes', 'boolean'], 'commandId' => ['required', 'string', 'max:64'], 'start' => ['prohibited'], 'finish' => ['prohibited']]);
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        $source = collect($tasks)->first(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId);
        abort_unless($source, 404, 'Tarefa não pertence ao projeto selecionado.');
        $attributes = array_filter(['content' => $data['title'], 'priority' => $data['priority'] ?? null], fn ($value) => $value !== null);
        $task = $gateway->updateTask($this->tokens->accessToken($integration), $taskId, $attributes);
        if (array_key_exists('completed', $data)) {
            $gateway->setTaskCompletion($this->tokens->accessToken($integration), $taskId, $data['completed']);
        }
        $audit->record($request->user()->id, $project->id, 'task.updated', 'user', 'todoist_task', $taskId, $data['commandId'], ['title' => $source['content'] ?? null, 'priority' => $source['priority'] ?? null, 'completed' => $source['is_completed'] ?? null], ['title' => $data['title'], 'priority' => $data['priority'] ?? $source['priority'] ?? null, 'completed' => $data['completed'] ?? $source['is_completed'] ?? null]);

        return response()->json(['data' => $task]);
    }

    public function updateDates(Request $request, string $taskId, TodoistGateway $gateway, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'commandId' => ['required', 'string', 'max:64'],
            'finish' => ['prohibited'],
            'deadline' => ['prohibited'],
        ]);
        [$project, $integration, $tasks] = $this->authorizedTasks($request, $gateway);
        $source = collect($tasks)->first(fn (array $task): bool => (string) ($task['id'] ?? '') === $taskId);
        abort_unless($source, 404, 'Tarefa não pertence ao projeto selecionado.');

        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $sourceStart = TodoistTask::start($source);
        $referenceStart = Carbon::createFromFormat('Y-m-d', $sourceStart ?? now($timezone)->toDateString(), $timezone)->startOfDay();
        $targetStart = Carbon::createFromFormat('Y-m-d', $data['start'], $timezone)->startOfDay();
        $dayDelta = (int) $referenceStart->diffInDays($targetStart, false);
        $sourceDeadline = TodoistTask::deadline($source);
        $targetDeadline = $sourceDeadline
            ? Carbon::createFromFormat('Y-m-d', $sourceDeadline, $timezone)->addDays($dayDelta)->toDateString()
            : null;

        $gateway->updateTaskDates($this->tokens->accessToken($integration), $taskId, $data['start'], $targetDeadline);
        $before = ['start' => $sourceStart, 'deadline' => $sourceDeadline];
        $after = ['start' => $data['start'], 'deadline' => $targetDeadline];
        $audit->record($request->user()->id, $project->id, 'task.dates_updated', 'user', 'todoist_task', $taskId, $data['commandId'], $before, $after);

        return response()->json(['data' => [
            'task_id' => $taskId,
            'start' => $data['start'],
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
    private function authorizedTasks(Request $request, TodoistGateway $gateway): array
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');
        $snapshot = $gateway->projectSnapshot($this->tokens->accessToken($integration), $project->todoist_project_id);

        return [$project, $integration, $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? []];
    }
}
