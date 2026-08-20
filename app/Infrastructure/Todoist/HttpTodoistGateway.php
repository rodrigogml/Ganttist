<?php

declare(strict_types=1);

namespace App\Infrastructure\Todoist;

use App\Contracts\TodoistGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

final class HttpTodoistGateway implements TodoistGateway
{
    private function client(string $token): PendingRequest
    {
        return Http::baseUrl(config('services.todoist.api_url'))->withToken($token)->acceptJson()->retry(3, 300, throw: false)->timeout(15);
    }

    public function projects(string $accessToken): array
    {
        return $this->client($accessToken)->get('/projects')->throw()->json();
    }

    public function projectSnapshot(string $accessToken, string $projectId): array
    {
        $baseUrl = rtrim((string) config('services.todoist.api_url'), '/');
        $responses = Http::pool(fn (Pool $pool): array => [
            $pool->as('sections')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl.'/sections', ['project_id' => $projectId, 'limit' => 200]),
            $pool->as('tasks')->withToken($accessToken)->acceptJson()->timeout(15)->get($baseUrl.'/tasks', ['project_id' => $projectId, 'limit' => 200]),
        ]);

        return [
            'project_id' => $projectId,
            'sections' => $this->allPages($accessToken, '/sections', $projectId, $responses['sections']->throw()->json()),
            'tasks' => $this->allPages($accessToken, '/tasks', $projectId, $responses['tasks']->throw()->json()),
        ];
    }

    /** @param array<string, mixed> $firstPage */
    private function allPages(string $accessToken, string $path, string $projectId, array $firstPage): array
    {
        $results = $firstPage['results'] ?? $firstPage;
        $cursor = $firstPage['next_cursor'] ?? null;
        while (is_string($cursor) && $cursor !== '') {
            $page = $this->client($accessToken)->get($path, ['project_id' => $projectId, 'limit' => 200, 'cursor' => $cursor])->throw()->json();
            $results = [...$results, ...($page['results'] ?? [])];
            $cursor = $page['next_cursor'] ?? null;
        }

        return ['results' => $results, 'next_cursor' => null];
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
        $this->client($accessToken)->post("/tasks/{$taskId}/".($completed ? 'close' : 'reopen'))->throw();
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
