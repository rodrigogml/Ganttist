<?php

namespace App\Services;

use App\Contracts\IncrementalTodoistGateway;
use App\Contracts\TodoistGateway;
use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\GroupScheduleCalculator;
use App\Domain\Scheduling\ProjectedTaskStatus;
use App\Domain\Scheduling\ProjectionPolicy;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\TaskProjectionCalculator;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Support\TodoistTask;
use DateTimeImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TodoistSyncService
{
    public function __construct(private readonly TodoistGateway $gateway, private readonly AuditWriter $audit, private readonly ProjectCalendarService $calendars, private readonly TodoistAccessTokenService $tokens, private readonly TodoistSnapshotStore $snapshots, private readonly TodoistUserIdentityService $identities) {}

    public function syncActiveProjects(): array
    {
        $synced = 0;
        $failed = 0;
        $integrations = DB::table('todoist_integrations')->where('status', 'active')->whereNotNull('access_token_encrypted')->get();
        foreach ($integrations as $integration) {
            $this->backfillTodoistUserId($integration);
            $projects = DB::table('gantt_projects')->where('user_id', $integration->user_id)->where('status', 'active')->get();
            $integrationFailed = false;
            foreach ($projects as $project) {
                try {
                    $snapshot = $this->snapshotForProject($integration, $project);
                    $this->synchronizeTaskAutomations($integration, $project, $snapshot, 'scheduler');
                    $this->publishSnapshotIfChanged($integration, $project, $snapshot);
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

    /** @return array{synced: bool, failed: bool} */
    public function syncUserProject(string $userId): array
    {
        $integration = DB::table('todoist_integrations')->where('user_id', $userId)->where('status', 'active')->whereNotNull('access_token_encrypted')->first();
        $project = DB::table('gantt_projects')->where('user_id', $userId)->where('status', 'active')->first();
        if (! $integration || ! $project) {
            throw new \RuntimeException('Conecte o Todoist e selecione um projeto primeiro.');
        }
        DB::table('todoist_integrations')->where('id', $integration->id)->update(['sync_state' => 'syncing', 'last_sync_error' => null, 'updated_at' => now()]);
        try {
            $snapshot = $this->snapshotForProject($integration, $project);
            $this->synchronizeTaskAutomations($integration, $project, $snapshot, 'user');
            $this->publishSnapshotIfChanged($integration, $project, $snapshot);
            DB::table('todoist_integrations')->where('id', $integration->id)->update(['last_synced_at' => now(), 'sync_state' => 'synced', 'last_sync_error' => null, 'updated_at' => now()]);

            return ['synced' => true, 'failed' => false];
        } catch (RequestException $exception) {
            $this->recordIntegrationFailure($integration, $exception, 'user');
            throw $exception;
        } catch (\Throwable $exception) {
            $this->markIntegrationDegraded($integration, 'remote_unavailable');
            throw $exception;
        }
    }

    public function markEvent(array $payload, bool $onlyIfNew = false): ?string
    {
        $todoistUserId = (string) ($payload['user_id'] ?? $payload['event_data']['user_id'] ?? '');
        $userId = $todoistUserId !== '' ? DB::table('todoist_integrations')->where('todoist_user_id', $todoistUserId)->value('user_id') : null;
        $externalId = (string) ($payload['delivery_id'] ?? $payload['event_id'] ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        $id = (string) Str::ulid();
        $inserted = DB::table('todoist_events')->insertOrIgnore(['id' => $id, 'external_event_id' => $externalId, 'user_id' => $userId, 'event_type' => (string) ($payload['event_name'] ?? 'unknown'), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted === 1) {
            if ($userId !== null) {
                DB::table('todoist_integrations')->where('user_id', $userId)->where('status', 'active')->update(['sync_state' => 'syncing', 'last_sync_error' => null, 'updated_at' => now()]);
            }

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
                $snapshot = $this->snapshotForProject($integration, $project);
                $this->synchronizeTaskAutomations($integration, $project, $snapshot, 'webhook', $event->external_event_id);
                $this->snapshots->put($project->id, $snapshot);
                $sourceTasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
                $ids = array_fill_keys(array_map(fn (array $task): string => (string) $task['id'], $sourceTasks), true);
                $this->reconcileProjectSnapshot($integration, $project, $ids, $event, $eventId);
                Cache::put($this->snapshotCacheKey($project->id), $this->snapshotFingerprint($snapshot), now()->addDays(7));
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

    private function backfillTodoistUserId(object $integration): void
    {
        if ($integration->todoist_user_id !== null || config('services.todoist.driver') !== 'http') {
            return;
        }
        try {
            $userId = $this->identities->resolve($this->tokens->accessToken($integration));
            if ($userId === null) {
                return;
            }
            DB::table('todoist_integrations')->where('id', $integration->id)->update(['todoist_user_id' => $userId, 'updated_at' => now()]);
            $integration->todoist_user_id = $userId;
        } catch (\Throwable $exception) {
            Log::warning('todoist.identity.backfill_failed', ['integration_id' => $integration->id, 'exception' => $exception::class]);
        }
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

    private function snapshotWithRetry(object $integration, string $projectId): array
    {
        $last = null;
        $token = $this->tokens->accessToken($integration);
        $authorizationRefreshed = false;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->gateway->projectSnapshot($token, $projectId);
            } catch (\Throwable $exception) {
                $last = $exception;
                if ($exception instanceof RequestException && $this->isAuthorizationFailure($exception)) {
                    if (! $authorizationRefreshed && $integration->refresh_token_encrypted) {
                        $token = $this->tokens->accessToken($integration, true);
                        $authorizationRefreshed = true;

                        continue;
                    }
                    throw $exception;
                }
                if ($attempt < 2) {
                    usleep(250000 * ($attempt + 1));
                }
            }
        }
        throw $last ?? new \RuntimeException('Falha de sincronização sem exceção.');
    }

    /** @return array<string, mixed> */
    private function snapshotForProject(object $integration, object $project): array
    {
        $snapshot = $this->snapshots->get($project->id);
        if (! $this->gateway instanceof IncrementalTodoistGateway) {
            return $this->snapshotWithRetry($integration, $project->todoist_project_id);
        }

        $token = is_string($integration->sync_token ?? null) && $integration->sync_token !== '' ? $integration->sync_token : '*';
        if ($snapshot === null) {
            $snapshot = $this->snapshotWithRetry($integration, $project->todoist_project_id);
        }

        $increment = $this->incrementWithRetry($integration, $token);
        $nextToken = $increment['sync_token'] ?? null;
        if (is_string($nextToken) && $nextToken !== '') {
            DB::table('todoist_integrations')->where('id', $integration->id)->update(['sync_token' => $nextToken, 'updated_at' => now()]);
            $integration->sync_token = $nextToken;
        }

        return $this->mergeIncrementalSnapshot($snapshot, $increment, $project->todoist_project_id);
    }

    /** @return array<string, mixed> */
    private function incrementWithRetry(object $integration, string $syncToken): array
    {
        $last = null;
        $token = $this->tokens->accessToken($integration);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->gateway->incrementalSync($token, $syncToken);
            } catch (\Throwable $exception) {
                $last = $exception;
                if ($attempt < 2) {
                    usleep(250000 * ($attempt + 1));
                }
            }
        }

        throw $last ?? new \RuntimeException('Falha de sincronização incremental sem exceção.');
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $increment */
    private function mergeIncrementalSnapshot(array $snapshot, array $increment, string $projectId): array
    {
        $merge = static function (array $current, array $changes, string $idKey = 'id'): array {
            $byId = [];
            foreach ($current as $item) {
                if (is_array($item) && isset($item[$idKey])) {
                    $byId[(string) $item[$idKey]] = $item;
                }
            }
            foreach ($changes as $item) {
                if (! is_array($item) || ! isset($item[$idKey])) {
                    continue;
                }
                $id = (string) $item[$idKey];
                if (($item['is_deleted'] ?? false) === true) {
                    unset($byId[$id]);
                } else {
                    $byId[$id] = [...($byId[$id] ?? []), ...$item];
                }
            }

            return array_values($byId);
        };

        $currentTasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
        $knownTaskIds = array_fill_keys(array_map(fn (array $task): string => (string) ($task['id'] ?? ''), $currentTasks), true);
        $changes = array_values(array_filter($increment['items'] ?? [], fn (mixed $item): bool => is_array($item) && (($item['project_id'] ?? null) === $projectId || isset($knownTaskIds[(string) ($item['id'] ?? '')]))));
        $snapshot['tasks'] = ['results' => $merge($currentTasks, $changes), 'next_cursor' => null];
        $currentSections = $snapshot['sections']['results'] ?? $snapshot['sections'] ?? [];
        $knownSectionIds = array_fill_keys(array_map(fn (array $section): string => (string) ($section['id'] ?? ''), $currentSections), true);
        $sectionChanges = array_values(array_filter($increment['sections'] ?? [], fn (mixed $item): bool => is_array($item) && (($item['project_id'] ?? null) === $projectId || isset($knownSectionIds[(string) ($item['id'] ?? '')]))));
        $snapshot['sections'] = ['results' => $merge($currentSections, $sectionChanges), 'next_cursor' => null];

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    private function publishSnapshotIfChanged(object $integration, object $project, array $snapshot): void
    {
        $cacheKey = $this->snapshotCacheKey($project->id);
        $fingerprint = $this->snapshotFingerprint($snapshot);
        $previous = Cache::get($cacheKey);
        Cache::put($cacheKey, $fingerprint, now()->addDays(7));
        $this->snapshots->put($project->id, $snapshot);
        if (is_string($previous) && hash_equals($previous, $fingerprint)) {
            return;
        }

        $this->audit->record($integration->user_id, $project->id, 'todoist.snapshot.reconciled', 'scheduler', 'gantt_project', $project->id, null, null, [
            'task_count' => count($snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? []),
            'scope' => 'project_snapshot',
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    private function snapshotFingerprint(array $snapshot): string
    {
        $tasks = collect($snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [])->sortBy(fn (array $task): string => (string) ($task['id'] ?? ''))->values()->all();
        $sections = collect($snapshot['sections']['results'] ?? $snapshot['sections'] ?? [])->sortBy(fn (array $section): string => (string) ($section['id'] ?? ''))->values()->all();

        return hash('sha256', json_encode(['tasks' => $tasks, 'sections' => $sections], JSON_THROW_ON_ERROR));
    }

    private function snapshotCacheKey(string $projectId): string
    {
        return 'todoist:snapshot:fingerprint:'.$projectId;
    }

    public function applyProjectAutomations(string $projectId): int
    {
        $project = DB::table('gantt_projects')->where('id', $projectId)->where('status', 'active')->first();
        if ($project === null) {
            return 0;
        }
        $integration = DB::table('todoist_integrations')->where('user_id', $project->user_id)->where('status', 'active')->first();
        if ($integration === null) {
            return 0;
        }
        $snapshot = $this->snapshotWithRetry($integration, $project->todoist_project_id);
        $updated = $this->synchronizeTaskAutomations($integration, $project, $snapshot, 'worker');
        if ($updated > 0) {
            $this->snapshots->put($project->id, $snapshot);
        }

        return $updated;
    }

    /** @param array<string, mixed> $snapshot */
    private function synchronizeTaskAutomations(object $integration, object $project, array &$snapshot, string $origin, ?string $causationId = null): int
    {
        $settings = DB::table('project_settings')->where('gantt_project_id', $project->id)->first();
        if ($settings === null) {
            return 0;
        }
        $scheduleBlockedTasks = (bool) ($settings->autoScheduleBlockedTasks ?? false);
        $clearParentTaskDates = (bool) ($settings->clearParentTaskDates ?? false);
        if (! $scheduleBlockedTasks && ! $clearParentTaskDates) {
            return 0;
        }
        if (! isset($snapshot['tasks']) || ! is_array($snapshot['tasks'])) {
            return 0;
        }
        $sourceTasks = &$snapshot['tasks'];
        $wrapped = isset($sourceTasks['results']);
        if ($wrapped) {
            $tasks = &$sourceTasks['results'];
        } else {
            $tasks = &$sourceTasks;
        }
        if (! is_array($tasks) || $tasks === []) {
            return 0;
        }

        $calendar = $this->calendars->forProject($project->id);
        $timezone = 'America/Sao_Paulo';
        $today = now($timezone)->startOfDay()->toDateTimeImmutable();
        $known = [];
        $children = [];
        foreach ($tasks as $index => $task) {
            $id = (string) ($task['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $known[$id] = $index;
            $parentId = (string) ($task['parent_id'] ?? '');
            if ($parentId !== '') {
                $children[$parentId] = true;
            }
        }

        $updated = 0;
        $token = null;
        if ($clearParentTaskDates) {
            foreach ($children as $parentId => $_hasChildren) {
                $index = $known[$parentId] ?? null;
                if ($index === null) {
                    continue;
                }
                $task = &$tasks[$index];
                $beforeStart = TodoistTask::start($task);
                $beforeDeadline = TodoistTask::deadline($task);
                if ($beforeStart === null && $beforeDeadline === null) {
                    unset($task);

                    continue;
                }
                $token ??= $this->tokens->accessToken($integration);
                $this->gateway->updateTask($token, $parentId, ['due_string' => 'no date', 'deadline_date' => null]);
                $task['due'] = null;
                $task['due_date'] = null;
                $task['deadline'] = null;
                $task['deadline_date'] = null;
                $this->audit->record($integration->user_id, $project->id, 'parent_task.dates.cleared', $origin, 'todoist_task', $parentId, $causationId, ['start' => $beforeStart, 'deadline' => $beforeDeadline], ['start' => null, 'deadline' => null]);
                $updated++;
                unset($task);
            }
        }

        if (! $scheduleBlockedTasks) {
            return $updated;
        }
        $completionOverrides = DB::table('task_metadata')->where('gantt_project_id', $project->id)->whereNotNull('completion_date_override')->pluck('completion_date_override', 'todoist_task_id')->all();
        $completionDate = function (array $task) use ($completionOverrides, $timezone): ?DateTimeImmutable {
            $id = (string) $task['id'];
            $value = $completionOverrides[$id] ?? TodoistTask::completionDate($task, $timezone);

            return $value ? new DateTimeImmutable((string) $value) : null;
        };
        $dependencies = [];
        foreach (DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->get() as $row) {
            if (isset($known[$row->predecessor_todoist_task_id], $known[$row->successor_todoist_task_id])) {
                $dependencies[] = new Dependency($row->predecessor_todoist_task_id, $row->successor_todoist_task_id, $row->type);
            }
        }
        if ($dependencies === []) {
            return $updated;
        }
        $projectionInputs = function (array $groupRanges = []) use (&$tasks, $completionDate): array {
            $inputs = [];
            foreach ($tasks as $task) {
                $id = (string) ($task['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $start = isset($groupRanges[$id]) ? $groupRanges[$id]->start : (($value = TodoistTask::start($task)) ? new DateTimeImmutable($value) : null);
                $deadline = isset($groupRanges[$id]) ? $groupRanges[$id]->finish : (($value = TodoistTask::deadline($task)) ? new DateTimeImmutable($value) : null);
                $inputs[] = new TaskProjectionInput($id, $start, $deadline, TodoistTask::completed($task), $completionDate($task));
            }

            return $inputs;
        };
        $calculator = new TaskProjectionCalculator($calendar, ProjectionPolicy::fromSetting($settings->projection_policy ?? null));
        $projections = $calculator->calculate($projectionInputs(), $dependencies, $today);
        $projectedPlans = [];
        foreach ($tasks as $task) {
            $id = (string) ($task['id'] ?? '');
            if ($id === '' || ! isset($projections[$id])) {
                continue;
            }
            $projection = $projections[$id];
            $parentId = isset($known[(string) ($task['parent_id'] ?? '')]) ? (string) $task['parent_id'] : null;
            $projectedPlans[$id] = TaskPlan::fromDates($id, (string) ($task['content'] ?? $id), $projection->consideredStart, $projection->consideredDeadline, $calendar, TodoistTask::completed($task), $projection->effectiveCompletionDate, $parentId);
        }
        $groups = (new GroupScheduleCalculator)->calculate($projectedPlans, $calendar);
        $projections = $calculator->calculate($projectionInputs($groups), $dependencies, $today);

        foreach ($tasks as &$task) {
            $id = (string) ($task['id'] ?? '');
            $projection = $projections[$id] ?? null;
            if ($projection === null || isset($children[$id]) || TodoistTask::completed($task) || $projection->status !== ProjectedTaskStatus::Blocked || $projection->unlockDate === null) {
                continue;
            }
            $targetStart = $projection->unlockDate->format('Y-m-d');
            $beforeStart = TodoistTask::start($task);
            $beforeDeadline = TodoistTask::deadline($task);
            $targetDeadline = $beforeDeadline !== null && $beforeDeadline < $targetStart ? $targetStart : $beforeDeadline;
            if ($beforeStart === $targetStart && $beforeDeadline === $targetDeadline) {
                continue;
            }
            $token ??= $this->tokens->accessToken($integration);
            $this->gateway->updateTaskDates($token, $id, $targetStart, $targetDeadline);
            $task['due'] = ['date' => $targetStart];
            if ($targetDeadline !== null) {
                $task['deadline_date'] = $targetDeadline;
            }
            $this->audit->record($integration->user_id, $project->id, 'blocked_task.start.automated', $origin, 'todoist_task', $id, $causationId, ['start' => $beforeStart, 'deadline' => $beforeDeadline], ['start' => $targetStart, 'deadline' => $targetDeadline, 'unlock_date' => $targetStart]);
            $updated++;
        }
        unset($task);

        return $updated;
    }
}
