<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreRevisionRequest;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\DocumentRevisionService;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DocumentRevisionController extends Controller
{
    public function __construct(private readonly ProjectAccess $access, private readonly DocumentRevisionService $service, private readonly DocumentPresenter $presenter) {}

    public function index(Request $request, string $projectId, string $documentId): JsonResponse
    {
        $this->access->document($request->user(), $projectId, $documentId);
        $revisions = DB::table('project_document_revisions')->where('document_id', $documentId)->orderByDesc('revision_sequence')->get()
            ->map(fn (object $revision): array => $this->presenter->revision($projectId, $revision))->all();

        return response()->json(['data' => $revisions]);
    }

    public function store(StoreRevisionRequest $request, string $projectId, string $documentId): JsonResponse
    {
        $this->access->document($request->user(), $projectId, $documentId, true);
        $data = $request->validated();
        $revision = $this->service->addRevision($projectId, $documentId, $request->user()->id, $data['revisionLabel'], $data['file']);

        return response()->json(['data' => $this->presenter->revision($projectId, $revision)], 201);
    }
}
