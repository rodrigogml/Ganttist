<?php

declare(strict_types=1);

namespace App\Contracts;

interface TodoistGateway
{
    public function projects(string $accessToken): array;

    public function projectSnapshot(string $accessToken, string $projectId): array;

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array;
}
