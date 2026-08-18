<?php

declare(strict_types=1);

namespace App\Contracts;

interface TodoistGateway
{
    public function projects(string $accessToken): array;

    public function projectSnapshot(string $accessToken, string $projectId): array;

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array;

    public function updateTask(string $accessToken, string $taskId, array $attributes): array;

    public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void;

    public function createTask(string $accessToken, array $attributes): array;

    public function deleteTask(string $accessToken, string $taskId): void;
}
