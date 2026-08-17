<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DependencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $dependencies = DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->get();

        return response()->json(['data' => $dependencies->map(fn (object $dependency): array => ['id' => $dependency->id, 'from' => $dependency->predecessor_todoist_task_id, 'to' => $dependency->successor_todoist_task_id, 'type' => $dependency->type, 'critical' => false])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:255'], 'to' => ['required', 'string', 'max:255', 'different:from'], 'type' => ['required', 'in:FS,SS,FF,SF']]);
        $project = $this->project($request);
        $id = (string) Str::ulid();
        DB::table('task_dependencies')->insert(['id' => $id, 'gantt_project_id' => $project->id, 'predecessor_todoist_task_id' => $data['from'], 'successor_todoist_task_id' => $data['to'], 'type' => $data['type'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'from' => $data['from'], 'to' => $data['to'], 'type' => $data['type']]], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = $this->project($request);
        $deleted = DB::table('task_dependencies')->where('id', $id)->where('gantt_project_id', $project->id)->update(['status' => 'removed', 'updated_at' => now()]);
        abort_unless($deleted, 404, 'Dependência não encontrada.');

        return response()->json(['message' => 'Dependência removida.']);
    }

    private function project(Request $request): object
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');

        return $project;
    }
}
