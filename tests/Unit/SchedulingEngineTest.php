<?php

namespace Tests\Unit;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use DomainException;
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
        self::assertSame('2026-08-17',$result->tasks['A']->start->format('Y-m-d'));
        self::assertSame([],$result->changedTaskIds,'Data virtual não deve ser persistida automaticamente.');
    }
}
