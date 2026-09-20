<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DerivationRunController extends Controller
{
    public function __construct(private readonly ProjectAccess $access) {}

    public function __invoke(Request $request, string $projectId, string $runId): JsonResponse
    {
        $this->access->view($request->user(), $projectId);
        $run = DB::table('project_document_derivation_runs as runs')
            ->join('project_document_derivations as derivations', 'derivations.id', '=', 'runs.derivation_id')
            ->where('runs.id', $runId)->where('derivations.project_id', $projectId)
            ->first(['runs.*']) ?? abort(404);

        return response()->json(['data' => [
            'id' => $run->id, 'derivationId' => $run->derivation_id, 'sourceRevisionId' => $run->source_revision_id,
            'targetRevisionId' => $run->target_revision_id, 'status' => $run->status, 'attemptCount' => (int) $run->attempt_count,
            'errorCode' => $run->error_code, 'errorMessage' => $run->error_message, 'startedAt' => $run->started_at, 'finishedAt' => $run->finished_at,
        ]]);
    }
}
