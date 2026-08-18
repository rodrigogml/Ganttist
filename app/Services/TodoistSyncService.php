<?php

namespace App\Services;

use App\Contracts\TodoistGateway;
use App\Domain\Scheduling\GroupScheduleCalculator;
use App\Domain\Scheduling\TaskPlan;
use DateTimeImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TodoistSyncService
{
    public function __construct(private readonly TodoistGateway $gateway, private readonly AuditWriter $audit, private readonly ProjectCalendarService $calendars) {}

    public function syncActiveProjects(): array
    {
        $synced = 0;
        $failed = 0;
        $integrations = DB::table('todoist_integrations')->where('status', 'active')->whereNotNull('access_token_encrypted')->get();
        foreach ($integrations as $integration) {
            $projects = DB::table('gantt_projects')->where('user_id', $integration->user_id)->where('status', 'active')->get();
            $integrationFailed = false;
            foreach ($projects as $project) {
                try {
                    $snapshot = $this->snapshotWithRetry($this->gateway, decrypt($integration->access_token_encrypted), $project->todoist_project_id);
                    $this->synchronizeDerivedGroupDates($integration, $project, $snapshot, 'scheduler');
                    $synced++;
                } catch (RequestException $exception) {
                    $failed++;
                    $integrationFailed = true;
                    $this->recordIntegrationFailure($integration, $exception, 'scheduler');
                    break;
                } catch (\Throwable $exception) {
                    $failed++;
                    $integrationFailed = true;
                    $this->markIntegrationDegraded($integration, 'remote_unavailable');
                    Log::warning('todoist.sync.failed', ['project_id' => $project->id, 'exception' => $exception::class]);
                }
            }
            if (! $integrationFailed) {
                DB::table('todoist_integrations')->where('id', $integration->id)->update(['last_synced_at' => now(), 'sync_state' => 'synced', 'last_sync_error' => null, 'updated_at' => now()]);
            }
        }

        return compact('synced', 'failed');
    }

    public function markEvent(array $payload, bool $onlyIfNew = false): ?string
    {
        $todoistUserId = (string) ($payload['user_id'] ?? $payload['event_data']['user_id'] ?? '');
        $userId = $todoistUserId !== '' ? DB::table('todoist_integrations')->where('todoist_user_id', $todoistUserId)->value('user_id') : null;
        $externalId = (string) ($payload['event_id'] ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        $id = (string) Str::ulid();
        $inserted = DB::table('todoist_events')->insertOrIgnore(['id' => $id, 'external_event_id' => $externalId, 'user_id' => $userId, 'event_type' => (string) ($payload['event_name'] ?? 'unknown'), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted === 1) {
            return $id;
        }

        return $onlyIfNew ? null : DB::table('todoist_events')->where('external_event_id', $externalId)->value('id');
    }

    public function processEvent(string $eventId): bool
    {
        $event = DB::table('todoist_events')->where('id', $eventId)->first();
        if (! $event || $event->processed_at !== null) {
            return true;
        }
        $integration = DB::table('todoist_integrations')->where('user_id', $event->user_id)->where('status', 'active')->first();
        if (! $integration) {
            DB::table('todoist_events')->where('id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);

            return true;
        }
        try {
            $projects = $this->projectsForEvent($integration, json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR));
            foreach ($projects as $project) {
                $snapshot = $this->snapshotWithRetry($this->gateway, decrypt($integration->access_token_encrypted), $project->todoist_project_id);
                $this->synchronizeDerivedGroupDates($integration, $project, $snapshot, 'webhook', $event->external_event_id);
                $sourceTasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
                $ids = array_fill_keys(array_map(fn (array $task): string => (string) $task['id'], $sourceTasks), true);
                $this->reconcileProjectSnapshot($integration, $project, $ids, $event, $eventId);
            }
            DB::transaction(function () use ($eventId, $integration): void {
                DB::table('todoist_events')->where('id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
                DB::table('todoist_integrations')->where('id', $integration->id)->update(['last_synced_at' => now(), 'sync_state' => 'synced', 'last_sync_error' => null, 'updated_at' => now()]);
            });

            return true;
        } catch (RequestException $exception) {
            if ($this->isAuthorizationFailure($exception)) {
                DB::transaction(function () use ($eventId, $integration, $event, $exception): void {
                    $this->recordIntegrationFailure($integration, $exception, 'worker', $eventId, $event->external_event_id);
                    DB::table('todoist_events')->where('id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
                });

                return true;
            }
            $this->markIntegrationDegraded($integration, $exception->response?->status() === 429 ? 'rate_limited' : 'remote_unavailable');
            Log::warning('todoist.event.reconciliation_failed', ['event_id' => $eventId, 'exception' => $exception::class]);

            return false;
        } catch (\Throwable $exception) {
            Log::warning('todoist.event.reconciliation_failed', ['event_id' => $eventId, 'exception' => $exception::class]);

            return false;
        }
    }

    private function isAuthorizationFailure(RequestException $exception): bool
    {
        return in_array($exception->response?->status(), [401, 403], true);
    }

    private function recordIntegrationFailure(object $integration, RequestException $exception, string $origin, ?string $eventId = null, ?string $externalEventId = null): void
    {
        if ($this->isAuthorizationFailure($exception)) {
            DB::table('todoist_integrations')->where('id', $integration->id)->update(['status' => 'reauthorization_required', 'sync_state' => 'reauthorization_required', 'last_sync_error' => 'authorization_revoked', 'updated_at' => now()]);
            $this->audit->record($integration->user_id, null, 'todoist.integration.reauthorization_required', $origin, $eventId === null ? 'todoist_integration' : 'todoist_event', $eventId ?? $integration->id, $externalEventId, null, ['status' => $exception->response?->status()]);

            return;
        }

        $this->markIntegrationDegraded($integration, $exception->response?->status() === 429 ? 'rate_limited' : 'remote_unavailable');
    }

    private function markIntegrationDegraded(object $integration, string $error): void
    {
        DB::table('todoist_integrations')->where('id', $integration->id)->where('status', 'active')->update(['sync_state' => 'degraded', 'last_sync_error' => $error, 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function projectsForEvent(object $integration, array $payload): Collection
    {
        $query = DB::table('gantt_projects')->where('user_id', $integration->user_id)->where('status', 'active');
        $eventData = is_array($payload['event_data'] ?? null) ? $payload['event_data'] : [];
        $projectIds = array_values(array_filter([
            $payload['project_id'] ?? null,
            $eventData['project_id'] ?? null,
            $eventData['parent_project_id'] ?? null,
            $eventData['old_project_id'] ?? null,
        ], fn (mixed $id): bool => is_string($id) && $id !== ''));

        return $projectIds === [] ? $query->get() : $query->whereIn('todoist_project_id', $projectIds)->get();
    }

    /** @param array<string, bool> $taskIds */
    private function reconcileProjectSnapshot(object $integration, object $project, array $taskIds, object $event, string $eventId): void
    {
        $ids = array_keys($taskIds);
        $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
        $eventData = is_array($payload['event_data'] ?? null) ? $payload['event_data'] : [];
        $affectedTaskId = (string) ($eventData['id'] ?? $payload['task_id'] ?? '');
        $isExplicitDeletion = str_contains(strtolower((string) $event->event_type), 'delete');

        DB::transaction(function () use ($integration, $project, $ids, $affectedTaskId, $isExplicitDeletion, $event, $eventId): void {
            DB::table('task_metadata')->where('gantt_project_id', $project->id)->whereIn('todoist_task_id', $ids)->update(['status' => 'active', 'updated_at' => now()]);
            DB::table('task_dependencies')->where('gantt_project_id', $project->id)->whereIn('predecessor_todoist_task_id', $ids)->whereIn('successor_todoist_task_id', $ids)->update(['status' => 'active', 'updated_at' => now()]);
            DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->where(function ($query) use ($ids): void {
                $query->whereNotIn('predecessor_todoist_task_id', $ids)->orWhereNotIn('successor_todoist_task_id', $ids);
            })->update(['status' => 'inactive', 'updated_at' => now()]);

            $missingMetadata = DB::table('task_metadata')->where('gantt_project_id', $project->id)->where('status', 'active')->whereNotIn('todoist_task_id', $ids);
            if ($isExplicitDeletion && $affectedTaskId !== '') {
                $missingMetadata->where('todoist_task_id', $affectedTaskId)->update(['status' => 'orphaned', 'updated_at' => now()]);
            } else {
                $missingMetadata->update(['status' => 'outside_project', 'updated_at' => now()]);
            }

            $this->audit->record($integration->user_id, $project->id, 'todoist.event.reconciled', 'webhook', 'todoist_event', $eventId, $event->external_event_id, null, ['task_count' => count($ids), 'scope' => 'project_snapshot']);
        });
    }

    private function snapshotWithRetry(TodoistGateway $gateway, string $token, string $projectId): array
    {
        $last = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $gateway->projectSnapshot($token, $projectId);
            } catch (\Throwable $exception) {
                $last = $exception;
                if ($exception instanceof RequestException && $this->isAuthorizationFailure($exception)) {
                    throw $exception;
                }
                if ($attempt < 2) {
                    usleep(250000 * ($attempt + 1));
                }
            }
        }
        throw $last ?? new \RuntimeException('Falha de sincronização sem exceção.');
    }

    /** @param array<string, mixed> $snapshot */
    private function synchronizeDerivedGroupDates(object $integration, object $project, array $snapshot, string $origin, ?string $causationId = null): void
    {
        $sourceTasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
        $known = array_fill_keys(array_map(fn (array $task): string => (string) ($task['id'] ?? ''), $sourceTasks), true);
        $calendar = $this->calendars->forProject($project->id);
        $plans = [];
        foreach ($sourceTasks as $task) {
            $id = (string) $task['id'];
            $start = isset($task['due']['date']) ? new DateTimeImmutable($task['due']['date']) : null;
            $deadline = isset($task['deadline_date']) ? new DateTimeImmutable($task['deadline_date']) : null;
            $parentId = isset($known[(string) ($task['parent_id'] ?? '')]) ? (string) $task['parent_id'] : null;
            $plans[$id] = TaskPlan::fromDates($id, (string) ($task['content'] ?? $id), $start, $deadline, $calendar, (bool) ($task['is_completed'] ?? false), null, $parentId);
        }
        $groups = (new GroupScheduleCalculator)->calculate($plans, $calendar);
        foreach ($groups as $groupId => $range) {
            $source = collect($sourceTasks)->first(fn (array $task): bool => (string) $task['id'] === $groupId);
            if ($source === null || ($source['is_completed'] ?? false)) {
                continue;
            }
            $start = $range->start->format('Y-m-d');
            $finish = $range->finish->format('Y-m-d');
            if (($source['due']['date'] ?? null) === $start && ($source['deadline_date'] ?? null) === $finish) {
                continue;
            }
            $this->gateway->updateTaskDates(decrypt($integration->access_token_encrypted), $groupId, $start, $finish);
            $this->audit->record($integration->user_id, $project->id, 'group.dates.derived', $origin, 'todoist_task', $groupId, $causationId, null, ['start' => $start, 'finish' => $finish]);
        }
    }
}
