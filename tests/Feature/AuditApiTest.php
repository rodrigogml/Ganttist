<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_history_is_paginated_filtered_and_isolated(): void
    {
        [$owner, $projectId] = $this->project();
        [$other, $otherProjectId] = $this->project();
        $this->event('01J00000000000000000000003', $owner, $projectId, 'calendar.updated', 'web', 'project_settings', $projectId);
        $this->event('01J00000000000000000000002', $owner, $projectId, 'recalculation.completed', 'worker', 'recalculation', 'operation-1');
        $this->event('01J00000000000000000000001', $owner, $projectId, 'task.updated', 'web', 'todoist_task', 'task-1');
        $this->event('01J00000000000000000000004', $other, $otherProjectId, 'task.updated', 'web', 'todoist_task', 'task-1');

        $response = $this->actingAs($owner)->getJson('/api/v1/audit-events?limit=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.action', 'calendar.updated')->assertJsonPath('meta.hasMore', true);
        $cursor = $response->json('meta.nextCursor');
        $this->actingAs($owner)->getJson('/api/v1/audit-events?cursor='.$cursor.'&taskId=task-1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subjectId', 'task-1')->assertJsonPath('data.0.action', 'task.updated');
    }

    /** @return array{User, string} */
    private function project(): array
    {
        $user = User::factory()->create();
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'p-'.$projectId, 'display_name' => 'Projeto', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $projectId];
    }

    private function event(string $id, User $user, string $projectId, string $action, string $origin, string $subjectType, string $subjectId): void
    {
        DB::table('audit_events')->insert(['id' => $id, 'user_id' => $user->id, 'gantt_project_id' => $projectId, 'action' => $action, 'origin' => $origin, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'causation_id' => 'cause-'.$id, 'after_state' => '{}', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
