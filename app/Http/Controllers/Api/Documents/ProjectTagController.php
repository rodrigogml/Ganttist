<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreTagRequest;
use App\Http\Requests\Documents\UpdateTagRequest;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectTagController extends Controller
{
    private const ROOT_SCOPE = '00000000000000000000000000';

    public function __construct(private readonly ProjectAccess $access) {}

    public function index(Request $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $tags = DB::table('project_tags')->where('project_id', $projectId)->whereNull('archived_at')->orderBy('name')->get()
            ->map(fn (object $tag): array => $this->present($tag))->all();

        return response()->json(['data' => $tags]);
    }

    public function store(StoreTagRequest $request, string $projectId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $data = $request->validated();
        $parent = $this->parent($projectId, $data['parentTagId'] ?? null);
        $id = (string) Str::ulid();
        $normalized = $this->normalize($data['name']);
        abort_if($normalized === '', 422, 'Nome de tag inválido.');
        abort_if(DB::table('project_tags')->where('project_id', $projectId)->where('scope_key', $parent?->id ?? self::ROOT_SCOPE)->where('normalized_name', $normalized)->exists(), 409, 'Já existe uma tag com este nome neste nível.');
        DB::table('project_tags')->insert([
            'id' => $id, 'project_id' => $projectId, 'parent_tag_id' => $parent?->id,
            'scope_key' => $parent?->id ?? self::ROOT_SCOPE, 'name' => trim($data['name']), 'normalized_name' => $normalized,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['data' => $this->present(DB::table('project_tags')->where('id', $id)->firstOrFail())], 201);
    }

    public function update(UpdateTagRequest $request, string $projectId, string $tagId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $tag = DB::table('project_tags')->where('project_id', $projectId)->where('id', $tagId)->whereNull('archived_at')->first() ?? abort(404);
        $data = $request->validated();
        $parentId = array_key_exists('parentTagId', $data) ? $data['parentTagId'] : $tag->parent_tag_id;
        abort_if($parentId === $tagId, 422, 'Uma tag não pode ser pai dela mesma.');
        $parent = $this->parent($projectId, $parentId);
        abort_if($parent && $this->descendsFrom($projectId, $parent->id, $tagId), 422, 'A alteração criaria um ciclo na hierarquia.');
        $name = trim($data['name'] ?? $tag->name);
        $normalized = $this->normalize($name);
        abort_if($normalized === '', 422, 'Nome de tag inválido.');
        abort_if(DB::table('project_tags')->where('project_id', $projectId)->where('scope_key', $parent?->id ?? self::ROOT_SCOPE)->where('normalized_name', $normalized)->where('id', '!=', $tagId)->exists(), 409, 'Já existe uma tag com este nome neste nível.');
        DB::table('project_tags')->where('id', $tagId)->update([
            'parent_tag_id' => $parent?->id, 'scope_key' => $parent?->id ?? self::ROOT_SCOPE,
            'name' => $name, 'normalized_name' => $normalized, 'updated_at' => now(),
        ]);

        return response()->json(['data' => $this->present(DB::table('project_tags')->where('id', $tagId)->firstOrFail())]);
    }

    public function destroy(Request $request, string $projectId, string $tagId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $tag = DB::table('project_tags')->where('project_id', $projectId)->where('id', $tagId)->whereNull('archived_at')->first() ?? abort(404);
        abort_if(DB::table('project_tags')->where('parent_tag_id', $tag->id)->whereNull('archived_at')->exists(), 409, 'Arquive primeiro as tags-filhas.');
        DB::table('project_tags')->where('id', $tagId)->update([
            'normalized_name' => $tag->normalized_name.'#'.$tag->id,
            'archived_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_document_tag')->where('tag_id', $tagId)->delete();

        return response()->json([], 204);
    }

    private function parent(string $projectId, ?string $parentId): ?object
    {
        if ($parentId === null) {
            return null;
        }

        return DB::table('project_tags')->where('project_id', $projectId)->where('id', $parentId)->whereNull('archived_at')->first() ?? abort(422, 'Tag-pai inválida.');
    }

    private function descendsFrom(string $projectId, string $tagId, string $ancestorId): bool
    {
        $visited = [];
        while ($tagId !== '') {
            if ($tagId === $ancestorId) {
                return true;
            }
            if (isset($visited[$tagId])) {
                return true;
            }
            $visited[$tagId] = true;
            $parent = DB::table('project_tags')->where('project_id', $projectId)->where('id', $tagId)->value('parent_tag_id');
            if (! is_string($parent)) {
                return false;
            }
            $tagId = $parent;
        }

        return false;
    }

    private function normalize(string $name): string
    {
        return Str::lower(Str::ascii((string) Str::of($name)->squish()));
    }

    /** @return array<string, mixed> */
    private function present(object $tag): array
    {
        return ['id' => $tag->id, 'name' => $tag->name, 'parentTagId' => $tag->parent_tag_id];
    }
}
