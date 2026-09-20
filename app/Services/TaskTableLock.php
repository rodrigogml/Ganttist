<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

final class TaskTableLock
{
    public const TTL_SECONDS = 10;

    private const MAX_ATTEMPTS_PER_MINUTE = 90;

    /** @return array{lock_token: string, expires_at: string} */
    public function acquire(string $projectId, string $taskId, string $tableId, string $userId): array
    {
        $this->guardRate($userId, $tableId);

        return DB::transaction(function () use ($projectId, $taskId, $tableId, $userId): array {
            $table = $this->tableForUpdate($projectId, $taskId, $tableId);
            if ($table->idEditLockUser !== null && $this->isActive($table)) {
                throw new TaskTableLockConflict('TABLE_LOCKED');
            }

            return $this->grant($tableId, $userId);
        });
    }

    /** @return array{lock_token: string, expires_at: string} */
    public function renew(string $projectId, string $taskId, string $tableId, string $userId, string $token): array
    {
        $this->guardRate($userId, $tableId);

        return DB::transaction(function () use ($projectId, $taskId, $tableId, $userId, $token): array {
            $table = $this->tableForUpdate($projectId, $taskId, $tableId);
            if (! $this->belongsTo($table, $userId, $token)) {
                throw new TaskTableLockConflict('TABLE_LOCK_LOST');
            }

            return $this->grant($tableId, $userId, $token);
        });
    }

    public function release(string $projectId, string $taskId, string $tableId, string $userId, string $token): void
    {
        DB::transaction(function () use ($projectId, $taskId, $tableId, $userId, $token): void {
            $table = $this->tableForUpdate($projectId, $taskId, $tableId);
            if (! $this->belongsTo($table, $userId, $token)) {
                throw new TaskTableLockConflict('TABLE_LOCK_LOST');
            }

            DB::table('project_taskTable')->where('id', $tableId)->update([
                'idEditLockUser' => null,
                'editLockTokenHash' => null,
                'editLockExpiresAt' => null,
                'updatedAt' => now(),
            ]);
        });
    }

    public function requireActive(string $projectId, string $taskId, string $tableId, string $userId, string $token): object
    {
        return $this->withActive($projectId, $taskId, $tableId, $userId, $token, fn (object $table): object => $table);
    }

    public function withActive(string $projectId, string $taskId, string $tableId, string $userId, string $token, Closure $operation): mixed
    {
        return DB::transaction(function () use ($projectId, $taskId, $tableId, $userId, $token, $operation): mixed {
            $table = $this->tableForUpdate($projectId, $taskId, $tableId);
            if (! $this->belongsTo($table, $userId, $token) || ! $this->isActive($table)) {
                throw new TaskTableLockConflict('TABLE_LOCK_LOST');
            }

            return $operation($table);
        });
    }

    private function guardRate(string $userId, string $tableId): void
    {
        $key = "task-table-lock:{$userId}:{$tableId}";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw new TaskTableLockRateLimited;
        }

        RateLimiter::hit($key, 60);
    }

    private function tableForUpdate(string $projectId, string $taskId, string $tableId): object
    {
        $table = DB::table('project_taskTable')
            ->where('id', $tableId)
            ->where('idProject', $projectId)
            ->where('idTask', $taskId)
            ->lockForUpdate()
            ->first();

        if ($table === null) {
            throw new TaskTableNotFound;
        }

        return $table;
    }

    /** @return array{lock_token: string, expires_at: string} */
    private function grant(string $tableId, string $userId, ?string $token = null): array
    {
        $token ??= bin2hex(random_bytes(32));
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);
        DB::table('project_taskTable')->where('id', $tableId)->update([
            'idEditLockUser' => $userId,
            'editLockTokenHash' => hash('sha256', $token),
            'editLockExpiresAt' => $expiresAt,
            'updatedAt' => now(),
        ]);

        return ['lock_token' => $token, 'expires_at' => $expiresAt->toIso8601String()];
    }

    private function belongsTo(object $table, string $userId, string $token): bool
    {
        return $table->idEditLockUser === $userId
            && is_string($table->editLockTokenHash)
            && hash_equals($table->editLockTokenHash, hash('sha256', $token));
    }

    private function isActive(object $table): bool
    {
        return $table->editLockExpiresAt !== null && now()->lt($table->editLockExpiresAt);
    }
}

final class TaskTableNotFound extends RuntimeException {}

final class TaskTableLockConflict extends RuntimeException {}

final class TaskTableLockRateLimited extends RuntimeException {}
