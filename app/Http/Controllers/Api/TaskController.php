<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TaskController extends Controller
{
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
