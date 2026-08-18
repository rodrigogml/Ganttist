<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuditWriter
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function record(?string $userId, ?string $projectId, string $action, string $origin, ?string $subjectType, ?string $subjectId, ?string $causationId, ?array $before = null, ?array $after = null): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::ulid(), 'user_id' => $userId, 'gantt_project_id' => $projectId, 'action' => $action, 'origin' => $origin, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'causation_id' => $causationId, 'before_state' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after_state' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
