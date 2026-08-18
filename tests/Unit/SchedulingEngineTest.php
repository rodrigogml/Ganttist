<?php

namespace Tests\Unit;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\GroupScheduleCalculator;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchedulingEngineTest extends TestCase
{
    private SchedulingEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SchedulingEngine(new WorkCalendar);
    }

    public function test_fs_cascade_preserves_duration_and_skips_weekend(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-20'), 2),
            new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-21'), 2),
            new TaskPlan('C', 'C', new DateTimeImmutable('2026-08-24'), 1),
        ], [new Dependency('A', 'B', 'FS'), new Dependency('B', 'C', 'FS')], new DateTimeImmutable('2026-08-16'));
        self::assertSame('2026-08-24', $result->tasks['B']->start->format('Y-m-d'));
        self::assertSame('2026-08-26', $result->tasks['C']->start->format('Y-m-d'));
        self::assertSame(['B', 'C'], $result->changedTaskIds);
    }

    public function test_strongest_of_multiple_constraints_wins(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-17'), 2),
            new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-20'), 2),
            new TaskPlan('C', 'C', new DateTimeImmutable('2026-08-17'), 3),
        ], [new Dependency('A', 'C', 'FS'), new Dependency('B', 'C', 'FF')], new DateTimeImmutable('2026-08-16'));
        self::assertSame('2026-08-19', $result->tasks['C']->start->format('Y-m-d'));
        self::assertSame('2026-08-21', $result->tasks['C']->finish(new WorkCalendar)->format('Y-m-d'));
    }

    public function test_completed_task_is_never_moved(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-20'), 3),
            new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-18'), 1, true, new DateTimeImmutable('2026-08-18')),
        ], [new Dependency('A', 'B')], new DateTimeImmutable('2026-08-16'));
        self::assertSame('2026-08-18', $result->tasks['B']->start->format('Y-m-d'));
        self::assertSame([], $result->changedTaskIds);
    }

    public function test_cycle_is_rejected_without_partial_result(): void
    {
        $this->expectException(DomainException::class);
        $this->engine->schedule([new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-17')), new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-18'))], [new Dependency('A', 'B'), new Dependency('B', 'A')], new DateTimeImmutable('2026-08-16'));
    }

    public function test_unscheduled_task_receives_virtual_operational_date(): void
    {
        $result = $this->engine->schedule([new TaskPlan('A', 'A', null, 1)], [], new DateTimeImmutable('2026-08-16'));
        self::assertNull($result->tasks['A']->start, 'Tarefa não programada não pode ganhar data persistida no resultado público.');
        self::assertSame('2026-08-17', $result->virtualStarts['A']->format('Y-m-d'));
        self::assertSame([], $result->changedTaskIds, 'Data virtual não deve ser persistida automaticamente.');
    }

    public function test_group_ranges_are_derived_bottom_up_and_ignore_unscheduled_descendants(): void
    {
        $calendar = new WorkCalendar;
        $groups = (new GroupScheduleCalculator)->calculate([
            'G' => new TaskPlan('G', 'Grupo', null, 1, false, null, null),
            'N' => new TaskPlan('N', 'Grupo interno', null, 1, false, null, 'G'),
            'A' => new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-20'), 2, false, null, 'N'),
            'B' => new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-25'), 1, false, null, 'G'),
            'U' => new TaskPlan('U', 'U', null, 1, false, null, 'G'),
        ], $calendar);

        self::assertSame('2026-08-20', $groups['N']->start->format('Y-m-d'));
        self::assertSame('2026-08-25', $groups['G']->finish->format('Y-m-d'));
    }

    public function test_invalid_or_non_working_deadline_normalizes_duration(): void
    {
        $calendar = new WorkCalendar;
        $invalid = TaskPlan::fromDates('A', 'A', new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-19'), $calendar);
        $weekend = TaskPlan::fromDates('B', 'B', new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-23'), $calendar);

        self::assertSame(1, $invalid->duration);
        self::assertSame(2, $weekend->duration);
        self::assertSame('2026-08-21', $weekend->finish($calendar)->format('Y-m-d'));
    }

    #[DataProvider('precedenceCases')]
    public function test_forward_and_backward_pass_respect_every_precedence_type(string $type, string $predecessorStart, int $predecessorDuration, string $successorStart, int $successorDuration, string $expectedStart, array $critical): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'Predecessora', new DateTimeImmutable($predecessorStart), $predecessorDuration),
            new TaskPlan('B', 'Sucessora', new DateTimeImmutable($successorStart), $successorDuration),
        ], [new Dependency('A', 'B', $type)], new DateTimeImmutable('2026-08-16'));

        self::assertSame($expectedStart, $result->tasks['B']->start->format('Y-m-d'));
        self::assertSame($critical, $result->criticalTaskIds);
        foreach ($critical as $taskId) {
            self::assertSame(0, $result->totalFloat[$taskId]);
        }
    }

    public static function precedenceCases(): array
    {
        return [
            'finish-to-start' => ['FS', '2026-08-17', 3, '2026-08-17', 2, '2026-08-20', ['A', 'B']],
            'start-to-start' => ['SS', '2026-08-17', 3, '2026-08-17', 2, '2026-08-17', ['A']],
            'finish-to-finish' => ['FF', '2026-08-17', 3, '2026-08-17', 2, '2026-08-18', ['A', 'B']],
            'start-to-finish' => ['SF', '2026-08-19', 1, '2026-08-17', 2, '2026-08-18', ['A', 'B']],
        ];
    }

    public function test_parallel_path_reports_workday_float_and_only_the_longest_route_as_critical(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'A', new DateTimeImmutable('2026-08-17'), 3),
            new TaskPlan('B', 'B', new DateTimeImmutable('2026-08-17'), 2),
            new TaskPlan('C', 'C', new DateTimeImmutable('2026-08-17'), 1),
        ], [new Dependency('A', 'B', 'FS')], new DateTimeImmutable('2026-08-16'));

        self::assertSame(0, $result->totalFloat['A']);
        self::assertSame(0, $result->totalFloat['B']);
        self::assertSame(4, $result->totalFloat['C']);
        self::assertSame(['A', 'B'], $result->criticalTaskIds);
    }

    public function test_completed_task_without_a_planned_start_uses_its_effective_completion_date_in_critical_path(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('A', 'Concluída', null, 1, true, new DateTimeImmutable('2026-08-19')),
            new TaskPlan('B', 'Sucessora', new DateTimeImmutable('2026-08-17'), 1),
        ], [new Dependency('A', 'B', 'FS')], new DateTimeImmutable('2026-08-16'));

        self::assertSame('2026-08-20', $result->tasks['B']->start->format('Y-m-d'));
        self::assertSame(0, $result->totalFloat['A']);
        self::assertContains('A', $result->criticalTaskIds);
    }

    public function test_group_predecessor_is_normalized_to_its_latest_descendant_for_finish_constraints(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('G', 'Grupo', null),
            new TaskPlan('A', 'Descendente cedo', new DateTimeImmutable('2026-08-17'), 1, false, null, 'G'),
            new TaskPlan('B', 'Descendente tardio', new DateTimeImmutable('2026-08-17'), 3, false, null, 'G'),
            new TaskPlan('C', 'Sucessora', new DateTimeImmutable('2026-08-17'), 1),
        ], [new Dependency('G', 'C', 'FS')], new DateTimeImmutable('2026-08-16'));

        self::assertSame('2026-08-20', $result->tasks['C']->start->format('Y-m-d'));
        self::assertNotContains('G', $result->criticalTaskIds);
        self::assertContains('B', $result->criticalTaskIds);
    }

    public function test_group_predecessor_uses_its_earliest_descendant_for_start_constraints(): void
    {
        $result = $this->engine->schedule([
            new TaskPlan('G', 'Grupo', null),
            new TaskPlan('A', 'Descendente cedo', new DateTimeImmutable('2026-08-19'), 1, false, null, 'G'),
            new TaskPlan('B', 'Descendente tarde', new DateTimeImmutable('2026-08-19'), 1, false, null, 'G'),
            new TaskPlan('C', 'Sucessora', new DateTimeImmutable('2026-08-17'), 1),
        ], [new Dependency('G', 'C', 'SS')], new DateTimeImmutable('2026-08-16'));

        self::assertSame('2026-08-19', $result->tasks['C']->start->format('Y-m-d'));
        self::assertContains('A', $result->criticalTaskIds);
    }

    public function test_group_cannot_be_a_dependency_successor_in_the_core(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->schedule([
            new TaskPlan('G', 'Grupo', null),
            new TaskPlan('A', 'Folha', new DateTimeImmutable('2026-08-17'), 1, false, null, 'G'),
        ], [new Dependency('A', 'G', 'FS')], new DateTimeImmutable('2026-08-16'));
    }
}
