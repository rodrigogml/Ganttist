<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TodoistGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TodoistController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();

        return response()->json(['connected' => $integration !== null, 'project' => $project ? ['id' => $project->id, 'todoist_project_id' => $project->todoist_project_id, 'name' => $project->display_name] : null]);
    }

    public function projects(Request $request, TodoistGateway $gateway): JsonResponse
    {
        $token = $this->token($request);
        $projects = $gateway->projects($token);

        return response()->json(['data' => $projects['results'] ?? $projects]);
    }

    public function selectProject(Request $request): JsonResponse
    {
        $data = $request->validate(['todoist_project_id' => ['required', 'string', 'max:64'], 'display_name' => ['required', 'string', 'max:255']]);
        $this->token($request);
        DB::transaction(function () use ($request, $data): void {
            $ganttProject = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('todoist_project_id', $data['todoist_project_id'])->first();
            if ($ganttProject) {
                DB::table('gantt_projects')->where('id', $ganttProject->id)->update(['display_name' => $data['display_name'], 'status' => 'active', 'updated_at' => now()]);
            } else {
                $projectId = (string) Str::ulid();
                DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $request->user()->id, 'todoist_project_id' => $data['todoist_project_id'], 'display_name' => $data['display_name'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $ganttProject = DB::table('gantt_projects')->where('id', $projectId)->first();
            }
            $settings = DB::table('project_settings')->where('gantt_project_id', $ganttProject->id)->exists();
            if ($settings) {
                DB::table('project_settings')->where('gantt_project_id', $ganttProject->id)->update(['updated_at' => now()]);
            } else {
                DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $ganttProject->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['message' => 'Projeto selecionado.'], 201);
    }

    private function token(Request $request): string
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration?->access_token_encrypted, 409, 'Conecte sua conta Todoist primeiro.');

        return decrypt($integration->access_token_encrypted);
    }
}
