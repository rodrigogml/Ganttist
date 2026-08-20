<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use App\Services\AuditWriter;
use App\Services\TodoistAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TodoistController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $storedIntegration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->first();
        $integration = $storedIntegration?->status === 'active' && $storedIntegration->access_token_encrypted ? $storedIntegration : null;
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $operations = $project ? DB::table('sync_operations')->where('gantt_project_id', $project->id) : null;
        $pendingOperations = $operations ? (clone $operations)->whereIn('state', ['pending', 'applying', 'partial'])->count() : 0;
        $conflictOperations = $operations ? (clone $operations)->where('state', 'conflict')->count() : 0;
        Log::debug('todoist.status.requested', ['user_id' => $request->user()->id, 'connected' => $integration !== null, 'has_project' => $project !== null, 'ready' => $integration !== null && $project !== null]);

        return response()->json(['connected' => $integration !== null, 'integration_status' => $storedIntegration?->status ?? 'disconnected', 'sync_state' => $storedIntegration?->sync_state ?? 'unknown', 'last_sync_error' => $storedIntegration?->last_sync_error, 'last_synced_at' => $storedIntegration?->last_synced_at, 'pending_operations' => $pendingOperations, 'conflict_operations' => $conflictOperations, 'project' => $project ? ['id' => $project->id, 'todoist_project_id' => $project->todoist_project_id, 'name' => $project->display_name] : null]);
    }

    public function projects(Request $request, TodoistGateway $gateway, TodoistAccessTokenService $tokens): JsonResponse
    {
        $token = $this->token($request, $tokens);
        $projects = $gateway->projects($token);

        return response()->json(['data' => $projects['results'] ?? $projects]);
    }

    public function selectProject(Request $request, TodoistGateway $gateway, AuditWriter $audit, TodoistAccessTokenService $tokens): JsonResponse
    {
        $data = $request->validate(['todoist_project_id' => ['required', 'string', 'max:64'], 'commandId' => ['required', 'string', 'max:64']]);
        $projects = $gateway->projects($this->token($request, $tokens));
        $project = collect($projects['results'] ?? $projects)->first(fn (array $project): bool => (string) ($project['id'] ?? '') === $data['todoist_project_id']);
        abort_unless($project, 422, 'O projeto selecionado não pertence à conta Todoist conectada.');
        $displayName = (string) ($project['name'] ?? $project['content'] ?? 'Projeto Todoist');
        $projectId = DB::transaction(function () use ($request, $data, $displayName): string {
            DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->where('todoist_project_id', '!=', $data['todoist_project_id'])->update(['status' => 'archived', 'updated_at' => now()]);
            $ganttProject = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('todoist_project_id', $data['todoist_project_id'])->first();
            if ($ganttProject) {
                DB::table('gantt_projects')->where('id', $ganttProject->id)->update(['display_name' => $displayName, 'status' => 'active', 'updated_at' => now()]);
            } else {
                $newProjectId = (string) Str::ulid();
                DB::table('gantt_projects')->insert(['id' => $newProjectId, 'user_id' => $request->user()->id, 'todoist_project_id' => $data['todoist_project_id'], 'display_name' => $displayName, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $ganttProject = DB::table('gantt_projects')->where('id', $newProjectId)->first();
            }
            $settings = DB::table('project_settings')->where('gantt_project_id', $ganttProject->id)->exists();
            if ($settings) {
                DB::table('project_settings')->where('gantt_project_id', $ganttProject->id)->update(['updated_at' => now()]);
            } else {
                DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $ganttProject->id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $ganttProject->id;
        });
        $audit->record($request->user()->id, $projectId, 'todoist.project.selected', 'user', 'gantt_project', $projectId, $data['commandId'], null, ['todoist_project_id' => $data['todoist_project_id'], 'display_name' => $displayName]);

        return response()->json(['message' => 'Projeto selecionado.'], 201);
    }

    public function disconnect(Request $request, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['commandId' => ['required', 'string', 'max:64']]);
        $projectId = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->value('id');
        DB::transaction(function () use ($request): void {
            DB::table('todoist_integrations')->where('user_id', $request->user()->id)->update(['status' => 'disconnected', 'access_token_encrypted' => null, 'refresh_token_encrypted' => null, 'access_token_expires_at' => null, 'updated_at' => now()]);
            DB::table('gantt_projects')->where('user_id', $request->user()->id)->update(['status' => 'archived', 'updated_at' => now()]);
        });
        $audit->record($request->user()->id, $projectId, 'todoist.integration.disconnected', 'user', 'todoist_integration', null, $data['commandId']);

        return response()->json(['message' => 'Conta Todoist desconectada.']);
    }

    private function token(Request $request, TodoistAccessTokenService $tokens): string
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');

        return $tokens->accessToken($integration);
    }
}
