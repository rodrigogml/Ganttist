<?php

declare(strict_types=1);

namespace App\Infrastructure\Todoist;

use App\Contracts\TodoistGateway;

final class FakeTodoistGateway implements TodoistGateway
{
    public function projects(string $accessToken): array
    {
        return ['results' => [['id' => 'fake-project', 'name' => 'Projeto de demonstração']]];
    }

    public function projectSnapshot(string $accessToken, string $projectId): array
    {
        return ['sections' => ['results' => [['id' => 'fake-section', 'name' => 'Entrega']]], 'tasks' => ['results' => [
            ['id' => 'fake-task-1', 'content' => 'Preparar primeira entrega', 'parent_id' => null, 'section_id' => 'fake-section', 'is_completed' => false, 'priority' => 2, 'due' => ['date' => '2026-08-17'], 'deadline_date' => '2026-08-19'],
            ['id' => 'fake-task-2', 'content' => 'Validar resultado', 'parent_id' => null, 'section_id' => 'fake-section', 'is_completed' => false, 'priority' => 1, 'due' => null, 'deadline_date' => null],
        ]]];
    }

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
    {
        return ['id' => $taskId, 'due' => ['date' => $start], 'deadline_date' => $deadline];
    }

    public function updateTask(string $accessToken, string $taskId, array $attributes): array
    {
        return ['id' => $taskId] + $attributes;
    }

    public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void
    {
    }
}
