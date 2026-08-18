<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'string', 'max:32'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50'], 'taskId' => ['nullable', 'string', 'max:255'], 'action' => ['nullable', 'string', 'max:80'], 'origin' => ['nullable', 'string', 'max:24'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $project = DB::table('gantt_projects')->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($project, 409, 'Selecione um projeto Todoist primeiro.');
        $limit = $data['limit'] ?? 20;
        $events = DB::table('audit_events')->where('user_id', $request->user()->id)->where('gantt_project_id', $project->id)
            ->when($data['cursor'] ?? null, fn ($query, string $cursor) => $query->where('id', '<', $cursor))
            ->when($data['taskId'] ?? null, fn ($query, string $taskId) => $query->where('subject_id', $taskId))
            ->when($data['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($data['origin'] ?? null, fn ($query, string $origin) => $query->where('origin', $origin))
            ->when($data['from'] ?? null, fn ($query, string $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, string $to) => $query->whereDate('occurred_at', '<=', $to))
            ->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $events->count() > $limit;
        $events = $events->take($limit)->values();

        return response()->json(['data' => $events->map(fn (object $event): array => ['id' => $event->id, 'action' => $event->action, 'origin' => $event->origin, 'subjectType' => $event->subject_type, 'subjectId' => $event->subject_id, 'causationId' => $event->causation_id, 'before' => $event->before_state ? json_decode($event->before_state, true, 512, JSON_THROW_ON_ERROR) : null, 'after' => $event->after_state ? json_decode($event->after_state, true, 512, JSON_THROW_ON_ERROR) : null, 'occurredAt' => $event->occurred_at])->all(), 'meta' => ['nextCursor' => $hasMore ? $events->last()?->id : null, 'hasMore' => $hasMore]]);
    }
}
