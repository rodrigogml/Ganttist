<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ListDocumentsRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\DocumentRevisionService;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly ProjectAccess $access,
        private readonly DocumentRevisionService $revisions,
        private readonly DocumentPresenter $presenter,
    ) {}

    public function index(ListDocumentsRequest $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $data = $request->validated();
        $query = DB::table('project_documents')->where('project_id', $projectId);
        ($data['archived'] ?? false) ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        if (filled($data['search'] ?? null)) {
            $query->where('name', 'like', '%'.trim($data['search']).'%');
        }
        if (filled($data['kind'] ?? null)) {
            $query->where('kind', $data['kind']);
        }
        if (filled($data['mimeFamily'] ?? null)) {
            $query->whereIn('current_revision_id', DB::table('project_document_revisions')->select('id')->where('mime_type', 'like', $data['mimeFamily'].'/%'));
        }
        if (filled($data['state'] ?? null)) {
            $this->applyStateFilter($query, $data['state']);
        }
        foreach (($data['tagIds'] ?? []) as $tagId) {
            $tagIds = $this->tagAndDescendants($projectId, $tagId);
            abort_if($tagIds === [], 422, 'Tag inválida para este projeto.');
            $query->whereExists(fn ($tags) => $tags->selectRaw('1')->from('project_document_tag')->whereColumn('project_document_tag.document_id', 'project_documents.id')->whereIn('project_document_tag.tag_id', $tagIds));
        }
        $page = $query->orderBy('name')->paginate((int) ($data['perPage'] ?? 30));

        return response()->json([
            'data' => collect($page->items())->map(fn (object $document): array => $this->presenter->one($document))->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function store(StoreDocumentRequest $request, string $projectId): JsonResponse
    {
        $this->access->edit($request->user(), $projectId);
        $data = $request->validated();
        $document = $this->revisions->createDocument($projectId, $request->user()->id, $data['name'], $data['revisionLabel'], $data['file'], $data['tagIds'] ?? []);

        return response()->json(['data' => $this->presenter->one($document)], 201);
    }

    public function show(Request $request, string $projectId, string $documentId): JsonResponse
    {
        $document = $this->access->document($request->user(), $projectId, $documentId);

        return response()->json(['data' => $this->presenter->one($document)]);
    }

    public function update(UpdateDocumentRequest $request, string $projectId, string $documentId): JsonResponse
    {
        $document = $this->access->document($request->user(), $projectId, $documentId, true);
        abort_if($document->archived_at !== null, 409, 'Documento arquivado.');
        $data = $request->validated();
        DB::transaction(function () use ($projectId, $documentId, $data): void {
            if (isset($data['name'])) {
                DB::table('project_documents')->where('id', $documentId)->update(['name' => trim($data['name']), 'updated_at' => now()]);
            }
            if (array_key_exists('tagIds', $data)) {
                $this->revisions->syncTags($projectId, $documentId, $data['tagIds']);
            }
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
        });

        return $this->show($request, $projectId, $documentId);
    }

    public function destroy(Request $request, string $projectId, string $documentId): JsonResponse
    {
        $document = $this->access->document($request->user(), $projectId, $documentId, true);
        abort_if(DB::table('project_document_derivations')->where('source_document_id', $documentId)->where('active', true)->exists(), 409, 'Desative ou arquive os documentos derivados antes de arquivar a origem.');
        DB::transaction(function () use ($projectId, $documentId, $document): void {
            if ($document->kind === 'derived') {
                DB::table('project_document_derivations')->where('target_document_id', $documentId)->update(['active' => false, 'updated_at' => now()]);
            }
            DB::table('project_documents')->where('id', $documentId)->update(['archived_at' => now(), 'updated_at' => now()]);
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
        });

        return response()->json([], 204);
    }

    public function restore(Request $request, string $projectId, string $documentId): JsonResponse
    {
        $document = $this->access->document($request->user(), $projectId, $documentId, true);
        $derivation = DB::table('project_document_derivations')->where('target_document_id', $documentId)->first();
        if ($document->kind === 'derived' && $derivation) {
            $sourceArchived = DB::table('project_documents')->where('id', $derivation->source_document_id)->whereNotNull('archived_at')->exists();
            abort_if($sourceArchived, 409, 'Restaure primeiro o documento de origem.');
        }
        DB::transaction(function () use ($projectId, $documentId, $derivation): void {
            DB::table('project_documents')->where('id', $documentId)->update(['archived_at' => null, 'updated_at' => now()]);
            if ($derivation) {
                DB::table('project_document_derivations')->where('id', $derivation->id)->update(['active' => true, 'updated_at' => now()]);
            }
            DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()]);
        });

        return $this->show($request, $projectId, $documentId);
    }

    /** @return list<string> */
    private function tagAndDescendants(string $projectId, string $root): array
    {
        if (! DB::table('project_tags')->where('project_id', $projectId)->where('id', $root)->whereNull('archived_at')->exists()) {
            return [];
        }
        $result = [$root];
        for ($offset = 0; $offset < count($result); $offset++) {
            foreach (DB::table('project_tags')->where('project_id', $projectId)->where('parent_tag_id', $result[$offset])->whereNull('archived_at')->pluck('id') as $id) {
                if (! in_array($id, $result, true)) {
                    $result[] = $id;
                }
            }
        }

        return $result;
    }

    private function applyStateFilter($query, string $state): void
    {
        if (in_array($state, ['queued', 'processing', 'failed'], true)) {
            $query->whereExists(fn ($runs) => $runs->selectRaw('1')
                ->from('project_document_derivations as state_derivations')
                ->join('project_document_derivation_runs as state_runs', 'state_runs.derivation_id', '=', 'state_derivations.id')
                ->whereColumn('state_derivations.target_document_id', 'project_documents.id')
                ->where('state_runs.status', $state)
                ->where('state_runs.id', '=', fn ($latest) => $latest->from('project_document_derivation_runs as latest_state_runs')
                    ->selectRaw('max(latest_state_runs.id)')
                    ->whereColumn('latest_state_runs.derivation_id', 'state_derivations.id')));

            return;
        }

        $outdated = fn ($rows) => $rows->selectRaw('1')
            ->from('project_document_derivations as outdated_derivations')
            ->join('project_documents as outdated_source', 'outdated_source.id', '=', 'outdated_derivations.source_document_id')
            ->join('project_document_revisions as outdated_current', 'outdated_current.id', '=', 'project_documents.current_revision_id')
            ->whereColumn('outdated_derivations.target_document_id', 'project_documents.id')
            ->whereColumn('outdated_current.source_revision_id', '!=', 'outdated_source.current_revision_id');
        if ($state === 'outdated') {
            $query->whereExists($outdated);

            return;
        }

        $query->whereNotNull('current_revision_id')->whereNotExists($outdated)
            ->whereNotExists(fn ($runs) => $runs->selectRaw('1')
                ->from('project_document_derivations as ready_derivations')
                ->join('project_document_derivation_runs as ready_runs', 'ready_runs.derivation_id', '=', 'ready_derivations.id')
                ->whereColumn('ready_derivations.target_document_id', 'project_documents.id')
                ->whereIn('ready_runs.status', ['queued', 'processing', 'failed'])
                ->where('ready_runs.id', '=', fn ($latest) => $latest->from('project_document_derivation_runs as latest_ready_runs')
                    ->selectRaw('max(latest_ready_runs.id)')
                    ->whereColumn('latest_ready_runs.derivation_id', 'ready_derivations.id')));
    }
}
