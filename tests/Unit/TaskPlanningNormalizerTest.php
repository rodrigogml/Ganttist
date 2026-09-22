<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scheduling\PlanningDriver;
use App\Domain\Scheduling\TaskPlanningNormalizer;
use App\Domain\Scheduling\TaskPlanningPatch;
use App\Domain\Scheduling\TaskPlanningState;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaskPlanningNormalizerTest extends TestCase
{
    public function test_it_normalizes_start_finish_and_duration_combinations_with_workdays(): void
    {
        $normalizer = new TaskPlanningNormalizer(new WorkCalendar);

        $fromDates = $normalizer->normalize($this->state(), new TaskPlanningPatch(
            hasStart: true,
            start: $this->date('2026-08-20'),
            hasFinish: true,
            finish: $this->date('2026-08-24'),
        ));
        $fromStartAndDuration = $normalizer->normalize($this->state(), new TaskPlanningPatch(
            hasStart: true,
            start: $this->date('2026-08-20'),
            hasDurationWorkdays: true,
            durationWorkdays: 3,
        ), PlanningDriver::Duration);
        $fromFinishAndDuration = $normalizer->normalize($this->state(), new TaskPlanningPatch(
            hasFinish: true,
            finish: $this->date('2026-08-21'),
            hasDurationWorkdays: true,
            durationWorkdays: 3,
        ), PlanningDriver::Duration);
        $durationOnly = $normalizer->normalize($this->state(), new TaskPlanningPatch(hasDurationWorkdays: true, durationWorkdays: 5));

        self::assertSame(3, $fromDates->durationWorkdays);
        self::assertSame('2026-08-24', $fromStartAndDuration->finish?->format('Y-m-d'));
        self::assertNull($fromFinishAndDuration->start);
        self::assertSame('2026-08-21', $fromFinishAndDuration->finish?->format('Y-m-d'));
        self::assertSame(3, $fromFinishAndDuration->durationWorkdays);
        self::assertNull($durationOnly->start);
        self::assertNull($durationOnly->finish);
        self::assertSame(5, $durationOnly->durationWorkdays);
    }

    public function test_it_uses_the_driver_to_preserve_the_intended_value(): void
    {
        $normalizer = new TaskPlanningNormalizer(new WorkCalendar);
        $persisted = $this->state('2026-08-20', '2026-08-21', 2);

        $fromStart = $normalizer->normalize($persisted, new TaskPlanningPatch(hasStart: true, start: $this->date('2026-08-24')), PlanningDriver::Start);
        $fromFinish = $normalizer->normalize($persisted, new TaskPlanningPatch(hasFinish: true, finish: $this->date('2026-08-25')), PlanningDriver::Finish);
        $fromDuration = $normalizer->normalize($persisted, new TaskPlanningPatch(hasDurationWorkdays: true, durationWorkdays: 3), PlanningDriver::Duration);

        self::assertSame('2026-08-25', $fromStart->finish?->format('Y-m-d'));
        self::assertSame(4, $fromFinish->durationWorkdays);
        self::assertSame('2026-08-24', $fromDuration->finish?->format('Y-m-d'));
    }

    public function test_it_keeps_the_other_planning_values_when_one_is_cleared(): void
    {
        $normalizer = new TaskPlanningNormalizer(new WorkCalendar);
        $persisted = $this->state('2026-08-20', '2026-08-24', 3);

        $withoutStart = $normalizer->normalize($persisted, new TaskPlanningPatch(hasStart: true));
        $withoutFinish = $normalizer->normalize($persisted, new TaskPlanningPatch(hasFinish: true));
        $withoutDuration = $normalizer->normalize($persisted, new TaskPlanningPatch(hasDurationWorkdays: true));

        self::assertNull($withoutStart->start);
        self::assertSame('2026-08-24', $withoutStart->finish?->format('Y-m-d'));
        self::assertSame(3, $withoutStart->durationWorkdays);
        self::assertSame('2026-08-20', $withoutFinish->start?->format('Y-m-d'));
        self::assertNull($withoutFinish->finish);
        self::assertSame(3, $withoutFinish->durationWorkdays);
        self::assertSame('2026-08-20', $withoutDuration->start?->format('Y-m-d'));
        self::assertSame('2026-08-24', $withoutDuration->finish?->format('Y-m-d'));
        self::assertNull($withoutDuration->durationWorkdays);
    }

    public function test_it_rejects_invalid_duration_dates_and_ambiguous_commands(): void
    {
        $normalizer = new TaskPlanningNormalizer(new WorkCalendar);

        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($this->state(), new TaskPlanningPatch(hasStart: true, start: $this->date('2026-08-20'), hasDurationWorkdays: true, durationWorkdays: 2));
    }

    public function test_it_rejects_duration_outside_the_allowed_range_and_finish_before_start(): void
    {
        $normalizer = new TaskPlanningNormalizer(new WorkCalendar);

        foreach ([0, 3651] as $duration) {
            try {
                $normalizer->normalize($this->state(), new TaskPlanningPatch(hasDurationWorkdays: true, durationWorkdays: $duration));
                self::fail('Duração inválida deveria ser rejeitada.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($this->state(), new TaskPlanningPatch(
            hasStart: true,
            start: $this->date('2026-08-21'),
            hasFinish: true,
            finish: $this->date('2026-08-20'),
        ));
    }

    private function state(?string $start = null, ?string $finish = null, ?int $durationWorkdays = null): TaskPlanningState
    {
        return new TaskPlanningState($start ? $this->date($start) : null, $finish ? $this->date($finish) : null, $durationWorkdays);
    }

    private function date(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }
}
