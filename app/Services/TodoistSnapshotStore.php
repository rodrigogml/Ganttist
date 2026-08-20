<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class TodoistSnapshotStore
{
    /** @param array<string, mixed> $snapshot */
    public function put(string $projectId, array $snapshot): void
    {
        Cache::put($this->key($projectId), $snapshot, now()->addSeconds(30));
    }

    /** @return array<string, mixed>|null */
    public function get(string $projectId): ?array
    {
        $snapshot = Cache::get($this->key($projectId));

        return is_array($snapshot) ? $snapshot : null;
    }

    private function key(string $projectId): string
    {
        return 'todoist:snapshot:data:'.$projectId;
    }
}
