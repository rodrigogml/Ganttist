<?php

namespace Tests\Unit;

use App\Domain\Scheduling\TaskPlanningState;
use App\Domain\Scheduling\WorkCalendar;
use App\Services\ProjectWorkspaceProjectionService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectWorkspaceProjectionServiceTest extends TestCase
{
    public function test_ephemeral_planning_override_reports_finite_project_delay_without_mutating_input(): void
    {
        $task = (object) [
            'id' => 'A', 'section_id' => null, 'title' => 'Entrega', 'planned_start' => '2026-09-28', 'planned_finish' => '2026-09-28',
            'plannedDurationWorkdays' => 1, 'completed_at' => null, 'recurrenceRule' => null,
        ];
        $service = new ProjectWorkspaceProjectionService;
        $calendar = new WorkCalendar;
        $before = $service->calculate([$task], [], [], new DateTimeImmutable('2026-09-25'), $calendar);
        $after = $service->calculate([$task], [], [], new DateTimeImmutable('2026-09-25'), $calendar, [
            'A' => new TaskPlanningState(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-01'), 1),
        ]);

        $impact = $service->compare($before, $after);

        self::assertSame('2026-09-28', $task->planned_start);
        self::assertSame(['A'], $impact['affectedTasks']);
        self::assertSame('2026-09-28', $impact['projectFinishBefore']);
        self::assertSame('2026-10-01', $impact['projectFinishAfter']);
        self::assertSame('critical', $impact['severity']);
    }

    public function test_preview_warns_when_a_task_loses_its_float_without_delaying_the_project(): void
    {
        $tasks = [
            (object) ['id' => 'A', 'section_id' => null, 'title' => 'Âncora', 'planned_start' => '2026-10-02', 'planned_finish' => '2026-10-02', 'plannedDurationWorkdays' => 1, 'completed_at' => null, 'recurrenceRule' => null],
            (object) ['id' => 'B', 'section_id' => null, 'title' => 'Rotina', 'planned_start' => '2026-09-28', 'planned_finish' => '2026-09-28', 'plannedDurationWorkdays' => 1, 'completed_at' => null, 'recurrenceRule' => null],
        ];
        $service = new ProjectWorkspaceProjectionService;
        $calendar = new WorkCalendar;
        $before = $service->calculate($tasks, [], [], new DateTimeImmutable('2026-09-25'), $calendar);
        $after = $service->calculate($tasks, [], [], new DateTimeImmutable('2026-09-25'), $calendar, [
            'B' => new TaskPlanningState(new DateTimeImmutable('2026-10-02'), new DateTimeImmutable('2026-10-02'), 1),
        ]);

        $impact = $service->compare($before, $after);

        self::assertSame('2026-10-02', $impact['projectFinishBefore']);
        self::assertSame('2026-10-02', $impact['projectFinishAfter']);
        self::assertSame(['B'], $impact['enteredCriticalPath']);
        self::assertSame('warning', $impact['severity']);
    }
}
