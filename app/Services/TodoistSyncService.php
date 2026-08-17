<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class TodoistSyncService
{
    public function markEvent(array $payload): void
    {
        $todoistUserId = (string) ($payload['user_id'] ?? $payload['event_data']['user_id'] ?? '');
        $userId = $todoistUserId !== '' ? DB::table('todoist_integrations')->where('todoist_user_id', $todoistUserId)->value('user_id') : null;
        $externalId = (string) ($payload['event_id'] ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        DB::table('todoist_events')->insertOrIgnore(['id' => (string) \Illuminate\Support\Str::ulid(), 'external_event_id' => $externalId, 'user_id' => $userId, 'event_type' => (string) ($payload['event_name'] ?? 'unknown'), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        if ($userId) DB::table('todoist_integrations')->where('user_id', $userId)->update(['last_synced_at' => null, 'updated_at' => now()]);
    }
}
