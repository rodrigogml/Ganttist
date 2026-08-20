<?php

declare(strict_types=1);

namespace App\Support;

final class TodoistTask
{
    /** @param array<string, mixed> $task */
    public static function start(array $task): ?string
    {
        return self::civilDate($task['due']['date'] ?? $task['due_date'] ?? null);
    }

    /** @param array<string, mixed> $task */
    public static function finish(array $task): ?string
    {
        return self::deadline($task) ?? self::start($task);
    }

    /** @param array<string, mixed> $task */
    public static function deadline(array $task): ?string
    {
        return self::civilDate($task['deadline']['date'] ?? $task['deadline_date'] ?? null);
    }

    /** @param array<string, mixed> $task */
    public static function completed(array $task): bool
    {
        return (bool) ($task['is_completed'] ?? $task['checked'] ?? false);
    }

    private static function civilDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = trim($value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : null;
    }
}
