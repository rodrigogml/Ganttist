<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TaskRecurrencePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurrence_columns_and_occurrence_history_are_persisted_with_expected_integrity(): void
    {
        $owner = User::factory()->create();
        $completedBy = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'frequency' => 'weekly', 'weekdays' => [1]];

        $this->assertTrue(Schema::hasColumn('project_tasks', 'recurrenceRule'));
        $this->assertTrue(Schema::hasColumn('project_tasks', 'recurrenceCursor'));
        $this->assertTrue(Schema::hasColumn('project_tasks', 'recurrenceVersion'));
        $this->assertTrue(Schema::hasTable('projectTaskOccurrence'));

        DB::table('project_tasks')->where('id', $taskId)->update([
            'recurrenceRule' => json_encode($rule, JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
        ]);

        $occurrence = $this->occurrence($projectId, $taskId, $completedBy->id, $rule);
        DB::table('projectTaskOccurrence')->insert($occurrence);

        $this->assertDatabaseHas('project_tasks', [
            'id' => $taskId,
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
        ]);
        $this->assertDatabaseHas('projectTaskOccurrence', [
            'id' => $occurrence['id'],
            'projectId' => $projectId,
            'taskId' => $taskId,
            'logicalDate' => '2026-09-28',
            'completedByUserId' => $completedBy->id,
        ]);

        $completedBy->delete();

        $this->assertDatabaseHas('projectTaskOccurrence', [
            'id' => $occurrence['id'],
            'completedByUserId' => null,
        ]);
    }

    public function test_occurrence_history_enforces_logical_date_and_command_id_uniqueness(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'frequency' => 'weekly', 'weekdays' => [1]];
        $occurrence = $this->occurrence($projectId, $taskId, null, $rule);
        DB::table('projectTaskOccurrence')->insert($occurrence);

        try {
            DB::table('projectTaskOccurrence')->insert([...$occurrence, 'id' => (string) Str::ulid()]);
            self::fail('A unicidade de taskId e logicalDate deveria rejeitar a segunda ocorrência.');
        } catch (QueryException) {
            self::assertDatabaseCount('projectTaskOccurrence', 1);
        }

        try {
            DB::table('projectTaskOccurrence')->insert([
                ...$occurrence,
                'id' => (string) Str::ulid(),
                'logicalDate' => '2026-10-05',
            ]);
            self::fail('A unicidade de completionCommandId deveria rejeitar a segunda ocorrência.');
        } catch (QueryException) {
            self::assertDatabaseCount('projectTaskOccurrence', 1);
        }
    }

    public function test_existing_task_creation_and_duplication_default_to_no_recurrence_state(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);

        $copyId = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/duplicate")
            ->assertCreated()
            ->json('data.id');

        foreach ([$taskId, $copyId] as $id) {
            $this->assertDatabaseHas('project_tasks', [
                'id' => $id,
                'recurrenceRule' => null,
                'recurrenceCursor' => null,
                'recurrenceVersion' => 0,
            ]);
        }
        self::assertDatabaseCount('projectTaskOccurrence', 0);
    }

    public function test_project_summary_excludes_active_recurring_tasks_from_finite_progress(): void
    {
        $owner = User::factory()->create();
        [$projectId, $finiteTaskId] = $this->createProjectAndTask($owner);
        $recurringTaskId = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", [
            'title' => 'Rotina aberta',
        ])->assertCreated()->json('data.id');

        DB::table('project_tasks')->where('id', $finiteTaskId)->update(['completed_at' => '2026-09-25']);
        DB::table('project_tasks')->where('id', $recurringTaskId)->update([
            'recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed'], JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
        ]);

        $summary = $this->actingAs($owner)->getJson('/api/v1/projects')->assertOk()->json('data.0');

        self::assertSame(100, $summary['progress']);
        self::assertSame(1, $summary['completed']);
        self::assertSame(1, $summary['total']);
        self::assertSame(2, $summary['taskCount']);
    }

    public function test_workspace_exposes_recurring_occurrence_and_separates_finite_and_operational_stats(): void
    {
        $owner = User::factory()->create();
        [$projectId, $finiteTaskId] = $this->createProjectAndTask($owner);
        $recurringTaskId = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", ['title' => 'Rotina semanal'])
            ->assertCreated()
            ->json('data.id');
        DB::table('project_tasks')->where('id', $finiteTaskId)->update(['completed_at' => '2026-09-25']);
        DB::table('project_tasks')->where('id', $recurringTaskId)->update([
            'recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1]], JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 2,
            'planned_start' => '2026-09-29',
            'planned_finish' => '2026-09-29',
            'plannedDurationWorkdays' => 1,
        ]);
        DB::table('projectTaskOccurrence')->insert($this->occurrence($projectId, $recurringTaskId, $owner->id, ['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1]]));

        $workspace = $this->actingAs($owner)->getJson("/api/v1/projects/{$projectId}/workspace")
            ->assertOk()
            ->json('data');
        $recurring = collect($workspace['tasks'])->firstWhere('id', $recurringTaskId);

        self::assertFalse($recurring['participatesInFiniteNetwork']);
        self::assertSame('toda segunda', $recurring['recurrence']['expression']);
        self::assertSame('2026-09-28', $recurring['occurrence']['logicalDate']);
        self::assertTrue($recurring['occurrence']['snoozed']);
        self::assertSame(1, $recurring['occurrenceHistoryCount']);
        self::assertSame(1, $workspace['stats']['finite']['totalTasks']);
        self::assertSame(1, $workspace['stats']['operational']['recurringTaskCount']);
        self::assertSame(1, $workspace['stats']['operational']['snoozedRecurringOccurrenceCount']);
    }

    public function test_recurrence_api_interprets_saves_removes_and_ends_a_rule_with_version_control(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence/interpret", ['expression' => 'toda segunda'])
            ->assertOk()->assertJsonPath('data.canonicalExpression', 'toda segunda');
        $saved = $this->actingAs($owner)->putJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence", ['source' => 'expression', 'expression' => 'toda segunda', 'expectedRecurrenceVersion' => 0])
            ->assertOk()->assertJsonPath('data.recurrenceVersion', 1);
        self::assertSame('fixed', $saved->json('data.recurrence.rule.basis'));
        $this->actingAs($owner)->putJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence", ['source' => 'expression', 'expression' => 'toda sexta', 'expectedRecurrenceVersion' => 0])
            ->assertStatus(409);
        $this->actingAs($owner)->deleteJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence")
            ->assertOk()->assertJsonPath('data.recurrence', null);
        $this->actingAs($owner)->putJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence", ['source' => 'editor', 'rule' => ['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1]]])
            ->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/recurrence/complete-forever")
            ->assertOk()->assertJsonPath('data.taskCompleted', true);
        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'recurrenceRule' => null]);
    }

    public function test_recurrence_api_enforces_permissions_and_rejects_predecessor_task(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        [$projectId, $predecessor] = $this->createProjectAndTask($owner);
        $successor = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", ['title' => 'Sucessora'])->json('data.id');
        DB::table('project_members')->insert(['id' => (string) Str::ulid(), 'project_id' => $projectId, 'user_id' => $reader->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/dependencies", ['from' => $predecessor, 'to' => $successor, 'type' => 'FS'])->assertCreated();

        $this->actingAs($reader)->putJson("/api/v1/projects/{$projectId}/tasks/{$successor}/recurrence", ['source' => 'expression', 'expression' => 'toda segunda'])
            ->assertForbidden();
        $this->actingAs($owner)->putJson("/api/v1/projects/{$projectId}/tasks/{$predecessor}/recurrence", ['source' => 'expression', 'expression' => 'toda segunda'])
            ->assertStatus(422);
    }

    public function test_structure_move_rejects_a_recurring_task_that_would_become_section_predecessor(): void
    {
        $owner = User::factory()->create();
        [$projectId, $recurringTask] = $this->createProjectAndTask($owner);
        $section = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/sections", ['name' => 'Predecessoras'])->assertCreated()->json('data.id');
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", ['title' => 'Âncora finita', 'sectionId' => $section])->assertCreated();
        $successor = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", ['title' => 'Sucessora'])->assertCreated()->json('data.id');
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/dependencies", ['from' => $section, 'fromKind' => 'section', 'to' => $successor, 'type' => 'FS'])->assertCreated();
        $this->actingAs($owner)->putJson("/api/v1/projects/{$projectId}/tasks/{$recurringTask}/recurrence", ['source' => 'expression', 'expression' => 'toda segunda'])->assertOk();

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/structure/move", [
            'itemId' => $recurringTask,
            'itemKind' => 'task',
            'parentSectionId' => $section,
        ])->assertStatus(422);

        $this->assertDatabaseHas('project_tasks', ['id' => $recurringTask, 'section_id' => null]);
    }

    public function test_snooze_preview_is_non_persistent_and_confirmation_revalidates_its_token(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        DB::table('project_tasks')->where('id', $taskId)->update([
            'recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed'], JSON_THROW_ON_ERROR),
            'recurrenceCursor' => now('America/Sao_Paulo')->toDateString(),
            'recurrenceVersion' => 1,
        ]);
        $payload = ['expectedLogicalDate' => now('America/Sao_Paulo')->toDateString(), 'option' => 'date', 'targetDate' => '2026-09-27'];
        $preview = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze-preview", $payload)
            ->assertOk()->assertJsonPath('data.normalizedTargetDate', '2026-09-28');
        self::assertNotNull($preview->json('data.workspaceRevision'));
        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'planned_start' => null]);
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze", [...$payload, 'previewToken' => $preview->json('data.previewToken')])
            ->assertOk()->assertJsonPath('data.occurrence.scheduledStart', '2026-09-28');
        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'planned_start' => '2026-09-28']);
    }

    public function test_legacy_completion_endpoint_cannot_bypass_a_recurring_occurrence_history(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        DB::table('project_tasks')->where('id', $taskId)->update([
            'recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1]], JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
        ]);

        $this->actingAs($owner)->patchJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/completion", ['completed' => true])
            ->assertStatus(422);
        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'completed_at' => null, 'recurrenceCursor' => '2026-09-28']);
        self::assertDatabaseCount('projectTaskOccurrence', 0);
    }

    public function test_snooze_rejects_invalid_preview_and_supports_only_approved_workday_options(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $cursor = now('America/Sao_Paulo')->toDateString();
        DB::table('project_tasks')->where('id', $taskId)->update(['recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed'], JSON_THROW_ON_ERROR), 'recurrenceCursor' => $cursor, 'recurrenceVersion' => 1]);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze-preview", ['expectedLogicalDate' => $cursor, 'option' => 'workdays', 'workdays' => 2])
            ->assertStatus(422);
        $preview = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze-preview", ['expectedLogicalDate' => $cursor, 'option' => 'workdays', 'workdays' => 3])
            ->assertOk();
        DB::table('project_tasks')->where('id', $taskId)->update(['recurrenceCursor' => '2026-10-05']);
        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze", ['expectedLogicalDate' => $cursor, 'option' => 'workdays', 'workdays' => 3, 'previewToken' => $preview->json('data.previewToken')])
            ->assertStatus(409);
    }

    public function test_snooze_rejects_a_preview_when_the_workspace_revision_changed(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $cursor = now('America/Sao_Paulo')->toDateString();
        DB::table('project_tasks')->where('id', $taskId)->update(['recurrenceRule' => json_encode(['version' => 1, 'basis' => 'fixed'], JSON_THROW_ON_ERROR), 'recurrenceCursor' => $cursor, 'recurrenceVersion' => 1]);
        $payload = ['expectedLogicalDate' => $cursor, 'option' => 'workdays', 'workdays' => 3];
        $preview = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze-preview", $payload)->assertOk();

        DB::table('projects')->where('id', $projectId)->update(['updated_at' => now()->addSecond()]);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/snooze", [...$payload, 'previewToken' => $preview->json('data.previewToken')])
            ->assertStatus(409);
        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'planned_start' => null]);
    }

    public function test_completing_an_occurrence_records_history_advances_the_cursor_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1]];
        DB::table('project_tasks')->where('id', $taskId)->update([
            'recurrenceRule' => json_encode($rule, JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
            'planned_start' => '2026-09-29',
            'planned_finish' => '2026-09-29',
            'plannedDurationWorkdays' => 1,
        ]);
        $payload = [
            'expectedLogicalDate' => '2026-09-28',
            'completionCommandId' => (string) Str::uuid(),
            'actualCompletionDate' => '2026-09-30',
        ];

        $first = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/complete", $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', false)
            ->assertJsonPath('data.completedOccurrence.logicalDate', '2026-09-28')
            ->assertJsonPath('data.completedOccurrence.scheduledStart', '2026-09-29')
            ->assertJsonPath('data.nextOccurrence.logicalDate', '2026-10-05');

        $this->assertDatabaseHas('project_tasks', [
            'id' => $taskId,
            'recurrenceCursor' => '2026-10-05',
            'planned_start' => '2026-10-05',
            'completed_at' => null,
        ]);
        $this->assertDatabaseHas('projectTaskOccurrence', [
            'taskId' => $taskId,
            'logicalDate' => '2026-09-28',
            'scheduledStart' => '2026-09-29',
            'completedAt' => '2026-09-30',
            'completedByUserId' => $owner->id,
            'completionCommandId' => $payload['completionCommandId'],
        ]);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/complete", $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', true)
            ->assertJsonPath('data.nextOccurrence.logicalDate', '2026-10-05');
        self::assertDatabaseCount('projectTaskOccurrence', 1);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/complete", [
            ...$payload,
            'completionCommandId' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->actingAs($owner)->getJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrences?limit=1")
            ->assertOk()
            ->assertJsonPath('data.items.0.logicalDate', '2026-09-28')
            ->assertJsonPath('data.nextCursor', null);

        self::assertNotNull($first->json('data.completedOccurrence.id'));
    }

    public function test_last_occurrence_at_the_rule_end_completes_the_task_definitively(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'basis' => 'fixed', 'frequency' => 'weekly', 'weekdays' => [1], 'endsOn' => '2026-09-28'];
        DB::table('project_tasks')->where('id', $taskId)->update([
            'recurrenceRule' => json_encode($rule, JSON_THROW_ON_ERROR),
            'recurrenceCursor' => '2026-09-28',
            'recurrenceVersion' => 1,
            'planned_start' => '2026-09-28',
            'planned_finish' => '2026-09-28',
            'plannedDurationWorkdays' => 1,
        ]);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrence/complete", [
            'expectedLogicalDate' => '2026-09-28',
            'completionCommandId' => (string) Str::uuid(),
            'actualCompletionDate' => '2026-09-28',
        ])->assertOk()
            ->assertJsonPath('data.taskCompleted', true)
            ->assertJsonPath('data.nextOccurrence', null);

        $this->assertDatabaseHas('project_tasks', ['id' => $taskId, 'recurrenceRule' => null, 'recurrenceCursor' => null, 'completed_at' => '2026-09-28']);
        self::assertDatabaseCount('projectTaskOccurrence', 1);
    }

    public function test_occurrence_history_uses_an_opaque_stable_cursor(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'frequency' => 'weekly', 'weekdays' => [1]];
        $first = $this->occurrence($projectId, $taskId, $owner->id, $rule);
        $second = [...$first, 'id' => (string) Str::ulid(), 'logicalDate' => '2026-10-05', 'completedAt' => '2026-10-07', 'completionCommandId' => (string) Str::uuid()];
        DB::table('projectTaskOccurrence')->insert([$first, $second]);

        $page = $this->actingAs($owner)->getJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrences?limit=1")
            ->assertOk()
            ->assertJsonPath('data.items.0.logicalDate', '2026-10-05');
        $cursor = $page->json('data.nextCursor');
        self::assertIsString($cursor);
        $this->actingAs($owner)->getJson("/api/v1/projects/{$projectId}/tasks/{$taskId}/occurrences?limit=1&cursor=".urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.items.0.logicalDate', '2026-09-28')
            ->assertJsonPath('data.nextCursor', null);
    }

    public function test_deleting_task_or_project_cascades_to_occurrence_history(): void
    {
        $owner = User::factory()->create();
        [$projectId, $taskId] = $this->createProjectAndTask($owner);
        $rule = ['version' => 1, 'frequency' => 'weekly', 'weekdays' => [1]];
        $taskOccurrence = $this->occurrence($projectId, $taskId, null, $rule);
        DB::table('projectTaskOccurrence')->insert($taskOccurrence);

        DB::table('project_tasks')->where('id', $taskId)->delete();

        $this->assertDatabaseMissing('projectTaskOccurrence', ['id' => $taskOccurrence['id']]);

        [$projectTaskProjectId, $projectTaskId] = $this->createProjectAndTask($owner, 'Projeto em cascata');
        $projectOccurrence = $this->occurrence($projectTaskProjectId, $projectTaskId, null, $rule);
        DB::table('projectTaskOccurrence')->insert($projectOccurrence);

        DB::table('projects')->where('id', $projectTaskProjectId)->delete();

        $this->assertDatabaseMissing('projectTaskOccurrence', ['id' => $projectOccurrence['id']]);
    }

    /** @return array{string, string} */
    private function createProjectAndTask(User $owner, string $projectName = 'Rotinas'): array
    {
        $projectId = $this->actingAs($owner)->postJson('/api/v1/projects', [
            'name' => $projectName,
            'commandId' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        $taskId = $this->actingAs($owner)->postJson("/api/v1/projects/{$projectId}/tasks", [
            'title' => 'Revisar indicadores',
        ])->assertCreated()->json('data.id');

        return [$projectId, $taskId];
    }

    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function occurrence(string $projectId, string $taskId, ?string $completedByUserId, array $rule): array
    {
        return [
            'id' => (string) Str::ulid(),
            'projectId' => $projectId,
            'taskId' => $taskId,
            'logicalDate' => '2026-09-28',
            'scheduledStart' => '2026-09-29',
            'scheduledFinish' => '2026-09-29',
            'completedAt' => '2026-09-30',
            'completedByUserId' => $completedByUserId,
            'recurrenceVersion' => 1,
            'recurrenceRuleSnapshot' => json_encode($rule, JSON_THROW_ON_ERROR),
            'completionCommandId' => (string) Str::uuid(),
            'createdAt' => now(),
            'updatedAt' => now(),
        ];
    }
}
