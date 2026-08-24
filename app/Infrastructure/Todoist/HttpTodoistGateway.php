<?php

declare(strict_types=1);

namespace App\Infrastructure\Todoist;

use App\Contracts\IncrementalTodoistGateway;
use App\Contracts\TodoistGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class HttpTodoistGateway implements IncrementalTodoistGateway, TodoistGateway
{
    private function client(string $token): PendingRequest
    {
        return Http::baseUrl(config('services.todoist.api_url'))->withToken($token)->acceptJson()->retry(
            3,
            function (int $_attempt, \Throwable $exception): int {
                if (! $exception instanceof RequestException) {
                    return 300;
                }

                $retryAfter = data_get($exception->response->json(), 'error_extra.retry_after');

                return $exception->response->serverError() && (is_int($retryAfter) || ctype_digit((string) $retryAfter)) ? (int) $retryAfter * 1000 : 300;
            },
            function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }
                if (! $exception instanceof RequestException) {
                    return false;
                }

                return $exception->response->serverError();
            },
            throw: false,
        )->timeout(15);
    }

    public function projects(string $accessToken): array
    {
        return $this->client($accessToken)->get('/projects')->throw()->json();
    }

    public function incrementalSync(string $accessToken, string $syncToken): array
    {
        return $this->client($accessToken)->asForm()->post('/sync', [
            'sync_token' => $syncToken,
            'resource_types' => json_encode(['projects', 'items', 'sections', 'collaborators'], JSON_THROW_ON_ERROR),
        ])->throw()->json();
    }

    public function projectSnapshot(string $accessToken, string $projectId): array
    {
        $startedAt = hrtime(true);
        $projectHash = substr(hash('sha256', $projectId), 0, 12);
        try {
            $baseUrl = rtrim((string) config('services.todoist.api_url'), '/');
            $poolStartedAt = hrtime(true);
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('project')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl."/projects/{$projectId}"),
                $pool->as('sections')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl.'/sections', ['project_id' => $projectId, 'limit' => 200]),
                $pool->as('tasks')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl.'/tasks', ['project_id' => $projectId, 'limit' => 200]),
                $pool->as('collaborators')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl."/projects/{$projectId}/collaborators", ['limit' => 200]),
            ]);
            $poolElapsedMs = $this->elapsedMs($poolStartedAt);

            $activeStartedAt = hrtime(true);
            $activeTasks = $this->allPages($accessToken, '/tasks', ['project_id' => $projectId], $responses['tasks']->throw()->json())['results'];
            $activeElapsedMs = $this->elapsedMs($activeStartedAt);
            $completedStartedAt = hrtime(true);
            $completedTasks = $this->completedTasks($accessToken, $projectId, $responses['project']->throw()->json());
            $completedElapsedMs = $this->elapsedMs($completedStartedAt);
            $tasksById = [];
            foreach ([...$completedTasks, ...$activeTasks] as $task) {
                if (is_array($task) && isset($task['id'])) {
                    $tasksById[(string) $task['id']] = $task;
                }
            }

            $snapshot = [
                'project_id' => $projectId,
                'sections' => $this->allPages($accessToken, '/sections', ['project_id' => $projectId], $responses['sections']->throw()->json()),
                'tasks' => ['results' => array_values($tasksById), 'next_cursor' => null],
                'collaborators' => $this->allPages($accessToken, "/projects/{$projectId}/collaborators", [], $responses['collaborators']->throw()->json()),
            ];
            Log::debug('todoist.snapshot.completed', [
                'project_hash' => $projectHash,
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'pool_elapsed_ms' => $poolElapsedMs,
                'active_tasks_elapsed_ms' => $activeElapsedMs,
                'completed_tasks_elapsed_ms' => $completedElapsedMs,
                'task_count' => count($tasksById),
            ]);

            return $snapshot;
        } catch (\Throwable $exception) {
            Log::warning('todoist.snapshot.failed', [
                'project_hash' => $projectHash,
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $project */
    private function completedTasks(string $accessToken, string $projectId, array $project): array
    {
        $until = CarbonImmutable::now('UTC')->addSecond();
        $createdAt = $project['created_at'] ?? null;
        try {
            $since = is_string($createdAt) && trim($createdAt) !== ''
                ? CarbonImmutable::parse($createdAt, 'UTC')->utc()
                : throw new \InvalidArgumentException('Project creation date is unavailable.');
        } catch (\Throwable) {
            $since = $until->subMonthsNoOverflow(3);
        }
        if ($since->greaterThanOrEqualTo($until)) {
            $since = $until->subMonthsNoOverflow(3);
        }

        $windows = [];
        while ($since->lessThan($until)) {
            $windowUntil = $since->addMonthsNoOverflow(3);
            if ($windowUntil->greaterThan($until)) {
                $windowUntil = $until;
            }
            $windows[] = [
                'project_id' => $projectId,
                'since' => $since->format('Y-m-d\TH:i:s\Z'),
                'until' => $windowUntil->format('Y-m-d\TH:i:s\Z'),
            ];
            $since = $windowUntil;
        }

        $completed = [];
        $baseUrl = rtrim((string) config('services.todoist.api_url'), '/');
        foreach (array_chunk($windows, 8, preserve_keys: true) as $windowBatch) {
            $responses = Http::pool(function (Pool $pool) use ($accessToken, $baseUrl, $windowBatch): array {
                $requests = [];
                foreach ($windowBatch as $index => $parameters) {
                    $requests[] = $pool->as('completed_'.$index)->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl.'/tasks/completed/by_completion_date', [...$parameters, 'limit' => 200]);
                }

                return $requests;
            });
            foreach ($windowBatch as $index => $parameters) {
                $page = $responses['completed_'.$index]->throw()->json();
                $completed = [...$completed, ...$this->allPages($accessToken, '/tasks/completed/by_completion_date', $parameters, $page, 'items')['results']];
            }
        }

        return array_map(static fn (array $task): array => [...$task, 'is_completed' => true, 'checked' => true], $completed);
    }

    /** @param array<string, mixed> $firstPage */
    private function allPages(string $accessToken, string $path, array $parameters, array $firstPage, string $itemsKey = 'results'): array
    {
        $results = $firstPage[$itemsKey] ?? $firstPage;
        $cursor = $firstPage['next_cursor'] ?? null;
        while (is_string($cursor) && $cursor !== '') {
            $page = $this->client($accessToken)->get($path, [...$parameters, 'limit' => 200, 'cursor' => $cursor])->throw()->json();
            $results = [...$results, ...($page[$itemsKey] ?? [])];
            $cursor = $page['next_cursor'] ?? null;
        }

        return ['results' => $results, 'next_cursor' => null];
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    public function comments(string $accessToken, string $taskId): array
    {
        $firstPage = $this->client($accessToken)->get('/comments', ['task_id' => $taskId, 'limit' => 200])->throw()->json();

        return $this->allPages($accessToken, '/comments', ['task_id' => $taskId], $firstPage);
    }

    public function createComment(string $accessToken, string $taskId, string $content): array
    {
        return $this->client($accessToken)->post('/comments', ['task_id' => $taskId, 'content' => $content])->throw()->json();
    }

    public function updateComment(string $accessToken, string $commentId, string $content): array
    {
        return $this->client($accessToken)->post("/comments/{$commentId}", ['content' => $content])->throw()->json();
    }

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
    {
        return $this->client($accessToken)->post("/tasks/{$taskId}", array_filter(['due_date' => $start, 'deadline_date' => $deadline], fn ($value) => $value !== null))->throw()->json();
    }

    public function updateTask(string $accessToken, string $taskId, array $attributes): array
    {
        return $this->client($accessToken)->post("/tasks/{$taskId}", $attributes)->throw()->json();
    }

    public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void
    {
        $commandId = (string) Str::uuid();
        $response = $this->client($accessToken)->asForm()->post('/sync', [
            'commands' => json_encode([[
                'type' => $completed ? 'item_close' : 'item_uncomplete',
                'uuid' => $commandId,
                'args' => ['id' => $taskId],
            ]], JSON_THROW_ON_ERROR),
        ]);
        $response->throw();
        if (data_get($response->json(), "sync_status.{$commandId}") !== 'ok') {
            throw new RequestException($response);
        }
    }

    public function createTask(string $accessToken, array $attributes): array
    {
        return $this->client($accessToken)->post('/tasks', $attributes)->throw()->json();
    }

    public function deleteTask(string $accessToken, string $taskId): void
    {
        $this->client($accessToken)->delete("/tasks/{$taskId}")->throw();
    }
}
