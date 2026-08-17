<?php

namespace App\Services;

use App\Contracts\TodoistGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TodoistSyncService
{
    public function syncActiveProjects(TodoistGateway $gateway): array
    {
        $synced = 0;
        $failed = 0;
        $integrations = DB::table('todoist_integrations')->where('status', 'active')->whereNotNull('access_token_encrypted')->get();
        foreach ($integrations as $integration) {
            $projects = DB::table('gantt_projects')->where('user_id', $integration->user_id)->where('status', 'active')->get();
            foreach ($projects as $project) {
                try {
                    $gateway->projectSnapshot(decrypt($integration->access_token_encrypted), $project->todoist_project_id);
                    $synced++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('todoist.sync.failed', ['project_id' => $project->id, 'exception' => $exception::class]);
                }
            }
            DB::table('todoist_integrations')->where('id', $integration->id)->update(['last_synced_at' => now(), 'updated_at' => now()]);
        }

        return compact('synced', 'failed');
    }

    public function markEvent(array $payload): void
    {
        $todoistUserId = (string) ($payload['user_id'] ?? $payload['event_data']['user_id'] ?? '');
        $userId = $todoistUserId !== '' ? DB::table('todoist_integrations')->where('todoist_user_id', $todoistUserId)->value('user_id') : null;
        $externalId = (string) ($payload['event_id'] ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        DB::table('todoist_events')->insertOrIgnore(['id' => (string) \Illuminate\Support\Str::ulid(), 'external_event_id' => $externalId, 'user_id' => $userId, 'event_type' => (string) ($payload['event_name'] ?? 'unknown'), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        if ($userId) DB::table('todoist_integrations')->where('user_id', $userId)->update(['last_synced_at' => null, 'updated_at' => now()]);
    }
}
