<?php

namespace App\Http\Controllers\Api;

use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectTaskViewController
{
    public function __construct(private readonly ProjectAccess $access) {}

    public function index(Request $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $this->provisionInitialViews($projectId, $request->user()->id);

        return response()->json(['data' => DB::table('project_task_views')
            ->where('project_id', $projectId)->where('owner_user_id', $request->user()->id)
            ->orderBy('created_at')->get()->map(fn (object $view): array => $this->viewPayload($view))]);
    }

    public function store(Request $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $data = $this->validatedView($request);
        $this->abortOnNameConflict($projectId, $request->user()->id, $data['name']);
        $view = $this->insertView($projectId, $request->user()->id, $data);

        return response()->json(['data' => $this->viewPayload($view)], 201);
    }

    public function update(Request $request, string $projectId, string $viewId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $view = $this->ownedView($projectId, $request->user()->id, $viewId);
        $data = $this->validatedView($request);
        $this->abortOnNameConflict($projectId, $request->user()->id, $data['name'], $viewId);
        DB::table('project_task_views')->where('id', $viewId)->update([
            'name' => $data['name'], 'query' => $data['query'], 'visual_state' => json_encode($data['visualState'], JSON_THROW_ON_ERROR),
            'format_version' => $data['formatVersion'], 'updated_at' => now(),
        ]);

        return response()->json(['data' => $this->viewPayload($this->ownedView($projectId, $request->user()->id, $viewId))]);
    }

    public function destroy(Request $request, string $projectId, string $viewId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $this->ownedView($projectId, $request->user()->id, $viewId);
        DB::table('project_task_views')->where('id', $viewId)->delete();

        return response()->json(null, 204);
    }

    public function import(Request $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $input = $request->validate(['file' => ['required', 'array'], 'conflictStrategy' => ['nullable', 'in:create,overwrite,cancel'], 'targetViewId' => ['nullable', 'string']]);
        $file = $input['file'];
        abort_unless(isset($file['name'], $file['query'], $file['visualState'], $file['formatVersion']) && is_string($file['name']) && is_string($file['query']) && is_array($file['visualState']) && (int) $file['formatVersion'] === 1, 422, 'Arquivo de view inválido.');
        $data = ['name' => trim($file['name']), 'query' => trim($file['query']), 'visualState' => $file['visualState'], 'formatVersion' => 1];
        abort_if($data['name'] === '', 422, 'Arquivo de view inválido.');
        $this->assertQuery($data['query']);
        $existing = DB::table('project_task_views')->where('project_id', $projectId)->where('owner_user_id', $request->user()->id)->where('name', $data['name'])->first();
        $strategy = $input['conflictStrategy'] ?? null;
        if ($existing && ! $strategy) abort(409, 'Já existe uma view com este nome.');
        if ($strategy === 'cancel') return response()->json(['data' => null, 'cancelled' => true]);
        if ($existing && $strategy === 'overwrite') {
            $target = $this->ownedView($projectId, $request->user()->id, (string) ($input['targetViewId'] ?? ''));
            DB::table('project_task_views')->where('id', $target->id)->update(['name' => $data['name'], 'query' => $data['query'], 'visual_state' => json_encode($data['visualState'], JSON_THROW_ON_ERROR), 'format_version' => 1, 'updated_at' => now()]);
            return response()->json(['data' => $this->viewPayload($this->ownedView($projectId, $request->user()->id, $target->id)), 'warnings' => []]);
        }
        if ($existing && $strategy === 'create') $data['name'] = $this->copyName($projectId, $request->user()->id, $data['name']);
        abort_unless(! $existing || $strategy === 'create', 422, 'Estratégia de conflito inválida.');
        $view = $this->insertView($projectId, $request->user()->id, $data);

        return response()->json(['data' => $this->viewPayload($view), 'warnings' => []], 201);
    }

    private function provisionInitialViews(string $projectId, string $userId): void
    {
        foreach ([
            ['name' => 'Minhas Tarefas', 'query' => '(status:aberta | status:atrasada) & (responsavel:eu | responsavel:sem)'],
            ['name' => 'Tarefas Equipe', 'query' => '(status:aberta | status:atrasada) & (responsavel:outros | responsavel:sem)'],
        ] as $preset) {
            if (! DB::table('project_task_views')->where('project_id', $projectId)->where('owner_user_id', $userId)->where('name', $preset['name'])->exists()) {
                $this->insertView($projectId, $userId, [...$preset, 'visualState' => $this->defaultVisualState(), 'formatVersion' => 1]);
            }
        }
    }

    private function insertView(string $projectId, string $userId, array $data): object
    {
        $id = (string) Str::ulid();
        DB::table('project_task_views')->insert(['id' => $id, 'project_id' => $projectId, 'owner_user_id' => $userId, 'name' => $data['name'], 'query' => $data['query'], 'visual_state' => json_encode($data['visualState'], JSON_THROW_ON_ERROR), 'format_version' => $data['formatVersion'], 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('project_task_views')->where('id', $id)->firstOrFail();
    }

    private function validatedView(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'query' => ['present', 'nullable', 'string', 'max:4096'], 'visualState' => ['required', 'array'], 'formatVersion' => ['required', 'integer', 'in:1']]);
        $data['name'] = trim($data['name']);
        abort_if($data['name'] === '', 422, 'O nome da view é obrigatório.');
        $data['query'] = trim($data['query'] ?? '');
        $this->assertQuery($data['query']);
        return $data;
    }

    private function assertQuery(string $query): void
    {
        // Uma view sem consulta ainda é útil: ela preserva ordenação, agrupamento e layout.
        if ($query === '') return;
        $depth = 0; $quoted = false; $escaped = false;
        foreach (str_split($query) as $character) {
            if ($escaped) { $escaped = false; continue; }
            if ($character === '\\') { $escaped = true; continue; }
            if ($character === '"') { $quoted = ! $quoted; continue; }
            if (! $quoted && $character === '(') $depth++;
            if (! $quoted && $character === ')') { $depth--; abort_if($depth < 0, 422, 'Parêntese de fechamento inesperado.'); }
        }
        abort_if($escaped || $quoted || $depth !== 0 || preg_match('/(?:[&|!]\s*|\(\s*)$/', $query) === 1, 422, 'A sintaxe da consulta é inválida.');
    }

    private function ownedView(string $projectId, string $userId, string $viewId): object
    {
        return DB::table('project_task_views')->where('id', $viewId)->where('project_id', $projectId)->where('owner_user_id', $userId)->first() ?? abort(404);
    }

    private function abortOnNameConflict(string $projectId, string $userId, string $name, ?string $exceptId = null): void
    {
        $query = DB::table('project_task_views')->where('project_id', $projectId)->where('owner_user_id', $userId)->where('name', $name);
        if ($exceptId) $query->where('id', '!=', $exceptId);
        abort_if($query->exists(), 409, 'Já existe uma view com este nome.');
    }

    private function copyName(string $projectId, string $userId, string $name): string
    {
        $candidate = $name.' (cópia)'; $number = 2;
        while (DB::table('project_task_views')->where('project_id', $projectId)->where('owner_user_id', $userId)->where('name', $candidate)->exists()) {
            $candidate = $name." (cópia {$number})";
            $number++;
        }
        return $candidate;
    }

    private function viewPayload(object $view): array
    {
        return ['id' => $view->id, 'name' => $view->name, 'query' => $view->query, 'visualState' => json_decode($view->visual_state, true, flags: JSON_THROW_ON_ERROR), 'formatVersion' => (int) $view->format_version, 'createdAt' => $view->created_at, 'updatedAt' => $view->updated_at];
    }

    private function defaultVisualState(): array { return ['version' => 1, 'groupBy' => null, 'subgroupBy' => null, 'sortBy' => null, 'subsortBy' => null, 'columns' => [], 'hierarchy' => 'expanded', 'tasks' => [], 'gantt' => ['zoom' => 'week']]; }
}
