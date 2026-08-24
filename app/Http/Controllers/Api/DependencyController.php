<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditWriter;
use App\Services\DependencyScopeValidator;
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

    public function store(Request $request, DependencyScopeValidator $scopeValidator, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:255'], 'to' => ['required', 'string', 'max:255', 'different:from'], 'type' => ['required', 'in:FS,SS,FF,SF'], 'commandId' => ['required', 'string', 'max:64']]);
        $project = $this->project($request);
        $dependencyByKey = fn () => DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('predecessor_todoist_task_id', $data['from'])->where('successor_todoist_task_id', $data['to'])->where('type', $data['type']);
        abort_if($dependencyByKey()->where('status', 'active')->exists(), 422, 'Essa dependência já existe.');
        $integration = DB::table('todoist_integrations')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($integration, 409, 'Conecte uma integração Todoist ativa antes de criar dependências.');
        try {
            $scopeValidator->validate($project, $integration, $data['from'], $data['to']);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'Não foi possível validar as tarefas no Todoist. Tente novamente.');
        }
        abort_if($this->wouldCycle($project->id, $data['from'], $data['to']), 422, 'Essa dependência criaria um ciclo no grafo.');
        $id = DB::transaction(function () use ($dependencyByKey, $project, $data): string {
            $existing = $dependencyByKey()->lockForUpdate()->first();
            abort_if($existing?->status === 'active', 422, 'Essa dependência já existe.');

            if ($existing) {
                $dependencyByKey()->update(['status' => 'active', 'updated_at' => now()]);

                return (string) $existing->id;
            }

            $id = (string) Str::ulid();
            DB::table('task_dependencies')->insert(['id' => $id, 'gantt_project_id' => $project->id, 'predecessor_todoist_task_id' => $data['from'], 'successor_todoist_task_id' => $data['to'], 'type' => $data['type'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
        $audit->record($request->user()->id, $project->id, 'dependency.created', 'user', 'task_dependency', $id, $data['commandId'], null, ['from' => $data['from'], 'to' => $data['to'], 'type' => $data['type']]);

        return response()->json(['data' => ['id' => $id, 'from' => $data['from'], 'to' => $data['to'], 'type' => $data['type']]], 201);
    }

    public function destroy(Request $request, string $id, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['commandId' => ['required', 'string', 'max:64']]);
        $project = $this->project($request);
        $dependency = DB::table('task_dependencies')->where('id', $id)->where('gantt_project_id', $project->id)->where('status', 'active')->first();
        abort_unless($dependency, 404, 'Dependência não encontrada.');
        DB::table('task_dependencies')->where('id', $id)->update(['status' => 'removed', 'updated_at' => now()]);
        $audit->record($request->user()->id, $project->id, 'dependency.deleted', 'user', 'task_dependency', $id, $data['commandId'], ['from' => $dependency->predecessor_todoist_task_id, 'to' => $dependency->successor_todoist_task_id, 'type' => $dependency->type], null);

        return response()->json(['message' => 'Dependência removida.']);
    }

    private function project(Request $request): object
    {
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');

        return $project;
    }

    private function wouldCycle(string $projectId, string $from, string $to): bool
    {
        $edges = DB::table('task_dependencies')->where('gantt_project_id', $projectId)->where('status', 'active')->get()->groupBy('predecessor_todoist_task_id');
        $seen = [];
        $pending = [$to];
        while ($pending) {
            $current = array_pop($pending);
            if ($current === $from) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($edges->get($current, []) as $edge) {
                $pending[] = $edge->successor_todoist_task_id;
            }
        }

        return false;
    }
}
