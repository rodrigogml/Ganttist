<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WorkspaceEventFeed
{
    /** @return Collection<int, object> */
    public function after(string $userId, string $afterEventId, int $limit = 50): Collection
    {
        return DB::table('audit_events')
            ->where('user_id', $userId)
            ->when($afterEventId !== '', fn ($query) => $query->where('id', '>', $afterEventId))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, string|null> */
    public function payload(object $event): array
    {
        return [
            'eventId' => $event->id,
            'projectId' => $event->gantt_project_id,
            'action' => $event->action,
            'causationId' => $event->causation_id,
            'occurredAt' => $event->occurred_at,
        ];
    }
}
