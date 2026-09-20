<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentPresenter;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OfflineManifestController extends Controller
{
    public function __construct(private readonly ProjectAccess $access, private readonly DocumentPresenter $presenter) {}

    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $workspace = app(ProjectController::class)->workspace($request, $projectId)->getData(true)['data'];
        $documents = DB::table('project_documents')->where('project_id', $projectId)->whereNull('archived_at')->orderBy('name')->get()
            ->map(fn (object $document): array => $this->presenter->one($document))->all();
        $tags = DB::table('project_tags')->where('project_id', $projectId)->whereNull('archived_at')->orderBy('name')->get()
            ->map(fn (object $tag): array => ['id' => $tag->id, 'name' => $tag->name, 'parentTagId' => $tag->parent_tag_id])->all();
        $files = collect($documents)->pluck('currentRevision')->filter()->map(fn (array $revision): array => [
            'revisionId' => $revision['id'], 'url' => $revision['contentUrl'], 'sizeBytes' => $revision['sizeBytes'],
            'sha256' => $revision['sha256'], 'mimeType' => $revision['mimeType'],
        ])->values()->all();
        $projectUpdated = DB::table('projects')->where('id', $projectId)->value('updated_at');
        $version = hash('sha256', json_encode([$projectId, (string) $projectUpdated, collect($files)->pluck('sha256')->all()], JSON_THROW_ON_ERROR));

        return response()->json(['data' => [
            'schemaVersion' => 1, 'version' => $version, 'generatedAt' => now()->toIso8601String(),
            'userId' => $request->user()->id, 'projectId' => $projectId, 'workspace' => $workspace,
            'documents' => $documents, 'tags' => $tags, 'files' => $files,
            'totalBytes' => collect($files)->sum('sizeBytes'),
        ]]);
    }
}
