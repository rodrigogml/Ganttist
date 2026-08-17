<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TaskController extends Controller
{
    public function update(Request $request, string $taskId, TodoistGateway $gateway): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:500'], 'start' => ['nullable', 'date_format:Y-m-d'], 'finish' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'completed' => ['sometimes', 'boolean']]);
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');
        abort_unless(DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 409, 'Selecione um projeto Todoist primeiro.');
        $attributes = array_filter(['content' => $data['title'], 'priority' => $data['priority'] ?? null, 'due_date' => $data['start'] ?? null, 'deadline_date' => $data['finish'] ?? null], fn ($value) => $value !== null);
        $task = $gateway->updateTask(decrypt($integration->access_token_encrypted), $taskId, $attributes);
        if (array_key_exists('completed', $data)) $gateway->setTaskCompletion(decrypt($integration->access_token_encrypted), $taskId, $data['completed']);

        return response()->json(['data' => $task]);
    }

    public function updateDates(Request $request, string $taskId, TodoistGateway $gateway): JsonResponse
    {
        $data = $request->validate(['start' => ['required', 'date_format:Y-m-d'], 'finish' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start']]);
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');
        abort_unless(DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 409, 'Selecione um projeto Todoist primeiro.');

        $task = $gateway->updateTaskDates(decrypt($integration->access_token_encrypted), $taskId, $data['start'], $data['finish'] ?? null);

        return response()->json(['data' => $task]);
    }
}
