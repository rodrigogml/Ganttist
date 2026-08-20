<?php

declare(strict_types=1);

namespace App\Support;

final class TodoistTask
{
    /** @param array<string, mixed> $task */
    public static function start(array $task): ?string
    {
        return $task['due']['date'] ?? $task['due_date'] ?? null;
    }

    /** @param array<string, mixed> $task */
    public static function finish(array $task): ?string
    {
        return $task['deadline']['date'] ?? $task['deadline_date'] ?? self::start($task);
    }

    /** @param array<string, mixed> $task */
    public static function completed(array $task): bool
    {
        return (bool) ($task['is_completed'] ?? $task['checked'] ?? false);
    }
}
