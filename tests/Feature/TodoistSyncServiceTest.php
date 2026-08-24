<?php

namespace Tests\Feature;

use App\Contracts\TodoistGateway;
use App\Jobs\ProcessTodoistEvent;
use App\Models\User;
use App\Services\TodoistSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TodoistSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_queues_a_single_idempotent_event_after_hmac_validation(): void
    {
        config()->set('services.todoist.webhook_secret', 'webhook-secret');
        config()->set('queue.default', 'database');
        Queue::fake();
        $payload = ['event_name' => 'item:updated', 'user_id' => 'unknown-user'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $json, 'webhook-secret', true));

        $server = ['HTTP_X_TODOIST_HMAC_SHA256' => $signature, 'HTTP_X_TODOIST_DELIVERY_ID' => 'delivery-webhook', 'CONTENT_TYPE' => 'application/json'];
        $this->call('POST', '/api/v1/webhooks/todoist', [], [], [], $server, $json)->assertOk();
        $this->call('POST', '/api/v1/webhooks/todoist', [], [], [], $server, $json)->assertOk();

        self::assertDatabaseCount('todoist_events', 1);
        self::assertSame('delivery-webhook', DB::table('todoist_events')->value('external_event_id'));
        Queue::assertPushed(ProcessTodoistEvent::class, 1);
    }

    public function test_webhook_rejects_validly_signed_malformed_json_without_persisting_an_event(): void
    {
        config()->set('services.todoist.webhook_secret', 'webhook-secret');
        $body = '{invalid';
        $signature = base64_encode(hash_hmac('sha256', $body, 'webhook-secret', true));

        $this->call('POST', '/api/v1/webhooks/todoist', [], [], [], ['HTTP_X_TODOIST_HMAC_SHA256' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(422);
        self::assertDatabaseCount('todoist_events', 0);
    }

    public function test_webhook_processes_the_event_when_the_sync_queue_driver_is_used(): void
    {
        config()->set('services.todoist.webhook_secret', 'webhook-secret');
        config()->set('queue.default', 'sync');
        $payload = ['event_id' => 'evt-sync-driver', 'event_name' => 'item:updated', 'user_id' => 'unknown-user'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $json, 'webhook-secret', true));

        $this->call('POST', '/api/v1/webhooks/todoist', [], [], [], ['HTTP_X_TODOIST_HMAC_SHA256' => $signature, 'CONTENT_TYPE' => 'application/json'], $json)->assertOk();

        self::assertNotNull(DB::table('todoist_events')->where('external_event_id', 'evt-sync-driver')->value('processed_at'));
    }

    public function test_webhook_event_is_deduplicated_and_reconciles_orphaned_dependencies(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'fake-user', 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'removed-task', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_metadata')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'todoist_task_id' => 'removed-task', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sync = app(TodoistSyncService::class);
        $eventId = $sync->markEvent(['event_id' => 'evt-1', 'event_name' => 'item:updated', 'user_id' => 'fake-user']);

        self::assertNotNull($eventId);
        self::assertTrue($sync->processEvent($eventId));
        self::assertSame($eventId, $sync->markEvent(['event_id' => 'evt-1', 'event_name' => 'item:updated', 'user_id' => 'fake-user']));
        self::assertNotNull(DB::table('todoist_events')->where('id', $eventId)->value('processed_at'));
        self::assertIsArray(cache()->get('todoist:snapshot:data:'.$projectId));
        self::assertDatabaseHas('task_dependencies', ['gantt_project_id' => $projectId, 'status' => 'inactive']);
        self::assertDatabaseHas('task_metadata', ['gantt_project_id' => $projectId, 'todoist_task_id' => 'removed-task', 'status' => 'outside_project']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'todoist.event.reconciled', 'causation_id' => 'evt-1']);
    }

    public function test_reconciliation_reactivates_items_that_reappear_in_the_authoritative_snapshot(): void
    {
        config()->set('services.todoist.driver', 'fake');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'restore-user', 'access_token_encrypted' => encrypt('fake'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'fake-project', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_metadata')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'todoist_task_id' => 'fake-task-2', 'status' => 'outside_project', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'fake-task-2', 'type' => 'FS', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()]);

        $eventId = app(TodoistSyncService::class)->markEvent(['event_id' => 'evt-restore', 'event_name' => 'item:updated', 'user_id' => 'restore-user', 'event_data' => ['project_id' => 'fake-project']]);

        self::assertTrue(app(TodoistSyncService::class)->processEvent($eventId));
        self::assertDatabaseHas('task_metadata', ['gantt_project_id' => $projectId, 'todoist_task_id' => 'fake-task-2', 'status' => 'active']);
        self::assertDatabaseHas('task_dependencies', ['gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'fake-task-1', 'successor_todoist_task_id' => 'fake-task-2', 'status' => 'active']);
    }

    public function test_revoked_token_marks_integration_for_reauthorization_without_retrying_the_event(): void
    {
        config()->set('services.todoist.driver', 'http');
        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);
        [$user, $eventId] = $this->activeIntegrationWithEvent('evt-revoked');

        self::assertTrue(app(TodoistSyncService::class)->processEvent($eventId));
        self::assertDatabaseHas('todoist_integrations', ['user_id' => $user->id, 'status' => 'reauthorization_required', 'sync_state' => 'reauthorization_required', 'last_sync_error' => 'authorization_revoked']);
        self::assertNotNull(DB::table('todoist_events')->where('id', $eventId)->value('processed_at'));
    }

    public function test_unauthorized_api_response_refreshes_the_token_and_retries_once(): void
    {
        config()->set('services.todoist.driver', 'http');
        config()->set('services.todoist.client_id', 'client');
        config()->set('services.todoist.client_secret', 'secret');
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.todoist.com/oauth/access_token') {
                return Http::response(['access_token' => 'new-token', 'refresh_token' => 'new-refresh-token', 'expires_in' => 3600]);
            }
            if ($request->hasHeader('Authorization', 'Bearer new-token')) {
                if (str_contains($request->url(), '/tasks/completed/by_completion_date')) {
                    return Http::response(['items' => []]);
                }
                if (str_contains($request->url(), '/projects/fake-project')) {
                    return Http::response(['id' => 'fake-project', 'created_at' => now()->subMonth()->toIso8601String()]);
                }

                return str_contains($request->url(), '/tasks') ? Http::response(['results' => []]) : Http::response([]);
            }

            return Http::response(['error' => 'Unauthorized'], 401);
        });
        [$user, $eventId] = $this->activeIntegrationWithEvent('evt-expired-access');
        DB::table('todoist_integrations')->where('user_id', $user->id)->update([
            'refresh_token_encrypted' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addHour(),
        ]);

        self::assertTrue(app(TodoistSyncService::class)->processEvent($eventId));
        $integration = DB::table('todoist_integrations')->where('user_id', $user->id)->first();
        self::assertSame('active', $integration->status);
        self::assertSame('new-token', decrypt($integration->access_token_encrypted));
        self::assertSame('new-refresh-token', decrypt($integration->refresh_token_encrypted));
        self::assertNotNull(DB::table('todoist_events')->where('id', $eventId)->value('processed_at'));
        // The snapshot is a four-request pool (project metadata, tasks, sections and collaborators),
        // retried once after refreshing the expired access token, followed by completed-task history
        // and the single incremental-sync request that establishes the next sync token.
        Http::assertSentCount(11);
    }

    public function test_rate_limit_keeps_event_pending_and_marks_sync_as_degraded(): void
    {
        config()->set('services.todoist.driver', 'http');
        Http::fake(['*' => Http::response(['error' => 'rate_limited'], 429)]);
        [$user, $eventId] = $this->activeIntegrationWithEvent('evt-rate-limit');

        self::assertFalse(app(TodoistSyncService::class)->processEvent($eventId));
        self::assertDatabaseHas('todoist_integrations', ['user_id' => $user->id, 'status' => 'active', 'sync_state' => 'degraded', 'last_sync_error' => 'rate_limited']);
        self::assertNull(DB::table('todoist_events')->where('id', $eventId)->value('processed_at'));
    }

    public function test_sync_never_writes_derived_dates_to_parent_tasks_by_default(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'project-groups', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gateway = new class implements TodoistGateway
        {
            public array $updates = [];

            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [['id' => 'parent', 'content' => 'Grupo', 'is_completed' => false, 'due' => null, 'deadline_date' => null], ['id' => 'child', 'content' => 'Filha', 'parent_id' => 'parent', 'is_completed' => false, 'due' => ['date' => '2026-08-17'], 'deadline_date' => '2026-08-19']]]];
            }

            public function comments(string $accessToken, string $taskId): array
            {
                return ['results' => []];
            }

            public function createComment(string $accessToken, string $taskId, string $content): array
            {
                return [];
            }

            public function updateComment(string $accessToken, string $commentId, string $content): array
            {
                return [];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                $this->updates[] = compact('taskId', 'start', 'deadline');

                return [];
            }

            public function updateTask(string $accessToken, string $taskId, array $attributes): array
            {
                return [];
            }

            public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

            public function createTask(string $accessToken, array $attributes): array
            {
                return [];
            }

            public function deleteTask(string $accessToken, string $taskId): void {}
        };
        app()->instance(TodoistGateway::class, $gateway);

        self::assertSame(['synced' => 1, 'failed' => 0], app(TodoistSyncService::class)->syncActiveProjects());
        self::assertSame([], $gateway->updates);
        self::assertDatabaseMissing('audit_events', ['gantt_project_id' => $projectId, 'action' => 'group.dates.derived']);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'action' => 'todoist.snapshot.reconciled', 'origin' => 'scheduler']);
        self::assertSame(['synced' => 1, 'failed' => 0], app(TodoistSyncService::class)->syncActiveProjects());
        self::assertSame(1, DB::table('audit_events')->where('gantt_project_id', $projectId)->where('action', 'todoist.snapshot.reconciled')->count());
    }

    public function test_enabled_automation_clears_dates_from_parent_tasks_without_setting_derived_values(): void
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'project-parent-clear', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'clearParentTaskDates' => true, 'created_at' => now(), 'updated_at' => now()]);
        $gateway = new class implements TodoistGateway
        {
            public array $dateUpdates = [];

            public array $taskUpdates = [];

            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [
                    ['id' => 'parent', 'content' => 'Grupo', 'is_completed' => false, 'due' => ['date' => '2026-08-17'], 'deadline_date' => '2026-08-19'],
                    ['id' => 'child', 'content' => 'Filha', 'parent_id' => 'parent', 'is_completed' => false, 'due' => ['date' => '2026-08-18'], 'deadline_date' => null],
                ]]];
            }

            public function comments(string $accessToken, string $taskId): array
            {
                return ['results' => []];
            }

            public function createComment(string $accessToken, string $taskId, string $content): array
            {
                return [];
            }

            public function updateComment(string $accessToken, string $commentId, string $content): array
            {
                return [];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                $this->dateUpdates[] = compact('taskId', 'start', 'deadline');

                return [];
            }

            public function updateTask(string $accessToken, string $taskId, array $attributes): array
            {
                $this->taskUpdates[] = compact('taskId', 'attributes');

                return [];
            }

            public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

            public function createTask(string $accessToken, array $attributes): array
            {
                return [];
            }

            public function deleteTask(string $accessToken, string $taskId): void {}
        };
        app()->instance(TodoistGateway::class, $gateway);

        self::assertSame(1, app(TodoistSyncService::class)->applyProjectAutomations($projectId));
        self::assertSame([], $gateway->dateUpdates);
        self::assertSame([['taskId' => 'parent', 'attributes' => ['due_string' => 'no date', 'deadline_date' => null]]], $gateway->taskUpdates);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'subject_id' => 'parent', 'action' => 'parent_task.dates.cleared', 'origin' => 'worker']);
    }

    public function test_enabled_automation_moves_a_blocked_leaf_to_its_unlock_date(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'project-automation', 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'autoScheduleBlockedTasks' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('task_dependencies')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'predecessor_todoist_task_id' => 'predecessor', 'successor_todoist_task_id' => 'blocked', 'type' => 'FS', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $gateway = new class implements TodoistGateway
        {
            public array $updates = [];

            public function projects(string $accessToken): array
            {
                return [];
            }

            public function projectSnapshot(string $accessToken, string $projectId): array
            {
                return ['tasks' => ['results' => [
                    ['id' => 'predecessor', 'content' => 'Predecessora', 'is_completed' => false, 'due' => ['date' => '2026-08-24'], 'deadline_date' => '2026-08-25'],
                    ['id' => 'blocked', 'content' => 'Bloqueada', 'is_completed' => false, 'due' => ['date' => '2026-08-20'], 'deadline_date' => '2026-08-22'],
                ]]];
            }

            public function comments(string $accessToken, string $taskId): array
            {
                return ['results' => []];
            }

            public function createComment(string $accessToken, string $taskId, string $content): array
            {
                return [];
            }

            public function updateComment(string $accessToken, string $commentId, string $content): array
            {
                return [];
            }

            public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
            {
                $this->updates[] = compact('taskId', 'start', 'deadline');

                return [];
            }

            public function updateTask(string $accessToken, string $taskId, array $attributes): array
            {
                return [];
            }

            public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

            public function createTask(string $accessToken, array $attributes): array
            {
                return [];
            }

            public function deleteTask(string $accessToken, string $taskId): void {}
        };
        app()->instance(TodoistGateway::class, $gateway);

        self::assertSame(1, app(TodoistSyncService::class)->applyProjectAutomations($projectId));
        self::assertSame([['taskId' => 'blocked', 'start' => '2026-08-26', 'deadline' => '2026-08-26']], $gateway->updates);
        self::assertDatabaseHas('audit_events', ['gantt_project_id' => $projectId, 'subject_id' => 'blocked', 'action' => 'blocked_task.start.automated', 'origin' => 'worker']);
        Carbon::setTestNow();
    }

    /** @return array{User, string} */
    private function activeIntegrationWithEvent(string $externalEventId): array
    {
        $user = User::factory()->create();
        DB::table('todoist_integrations')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_user_id' => 'todoist-'.$externalEventId, 'access_token_encrypted' => encrypt('token'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gantt_projects')->insert(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'todoist_project_id' => 'project-'.$externalEventId, 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $eventId = app(TodoistSyncService::class)->markEvent(['event_id' => $externalEventId, 'event_name' => 'item:updated', 'user_id' => 'todoist-'.$externalEventId]);

        self::assertNotNull($eventId);

        return [$user, $eventId];
    }
}
