<?php

declare(strict_types=1);

namespace App\Infrastructure\Todoist;

use App\Contracts\TodoistGateway;
use Illuminate\Http\Client\PendingRequest;
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
        return ['project_id' => $projectId, 'sections' => $this->client($accessToken)->get('/sections', ['project_id' => $projectId])->throw()->json(), 'tasks' => $this->client($accessToken)->get('/tasks', ['project_id' => $projectId])->throw()->json()];
    }

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
    {
        return $this->client($accessToken)->post("/tasks/{$taskId}", array_filter(['due_date' => $start, 'deadline_date' => $deadline], fn ($value) => $value !== null))->throw()->json();
    }
}
