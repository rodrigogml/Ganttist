<?php

namespace Tests\Unit;

use App\Domain\Recurrence\LogicalOccurrence;
use App\Domain\Recurrence\RecurrenceBasis;
use App\Domain\Recurrence\RecurrenceCalculator;
use App\Domain\Recurrence\RecurrenceFrequency;
use App\Domain\Recurrence\RecurrenceIntervalUnit;
use App\Domain\Recurrence\RecurrenceLifecycle;
use App\Domain\Recurrence\RecurrenceRule;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecurrenceCalculatorTest extends TestCase
{
    public function test_fixed_weekly_rule_preserves_its_phase_after_snooze_and_completion(): void
    {
        $rule = new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Weekly, weekdays: [1]);
        $next = (new RecurrenceCalculator)->next(
            $rule,
            new LogicalOccurrence(new DateTimeImmutable('2026-09-28')),
            new DateTimeImmutable('2026-09-29'),
            new DateTimeImmutable('2026-09-30'),
            new DateTimeImmutable('2026-09-30'),
            new WorkCalendar,
        );

        self::assertSame('2026-10-05', $next?->dateString());
    }

    public function test_interval_uses_the_later_scheduled_or_completion_date_in_workdays(): void
    {
        $rule = new RecurrenceRule(RecurrenceBasis::Interval, RecurrenceFrequency::Daily, 4, RecurrenceIntervalUnit::Workdays);
        $next = (new RecurrenceCalculator)->next(
            $rule,
            new LogicalOccurrence(new DateTimeImmutable('2026-09-28')),
            new DateTimeImmutable('2026-09-29'),
            new DateTimeImmutable('2026-09-28'),
            new DateTimeImmutable('2026-09-28'),
            new WorkCalendar,
        );

        self::assertSame('2026-10-05', $next?->dateString());
    }

    public function test_interval_handles_end_of_month_and_leap_day_and_skips_missed_dates(): void
    {
        $calculator = new RecurrenceCalculator;
        $monthly = new RecurrenceRule(RecurrenceBasis::Interval, RecurrenceFrequency::Monthly, 1, RecurrenceIntervalUnit::Months);
        $yearly = new RecurrenceRule(RecurrenceBasis::Interval, RecurrenceFrequency::Yearly, 1, RecurrenceIntervalUnit::Years);

        $endOfMonth = $calculator->next($monthly, new LogicalOccurrence(new DateTimeImmutable('2026-01-31')), new DateTimeImmutable('2026-01-31'), new DateTimeImmutable('2026-01-31'), new DateTimeImmutable('2026-01-31'), new WorkCalendar);
        $leapDay = $calculator->next($yearly, new LogicalOccurrence(new DateTimeImmutable('2024-02-29')), new DateTimeImmutable('2024-02-29'), new DateTimeImmutable('2024-02-29'), new DateTimeImmutable('2024-02-29'), new WorkCalendar);
        $skipped = $calculator->next($monthly, new LogicalOccurrence(new DateTimeImmutable('2026-01-15')), new DateTimeImmutable('2026-01-15'), new DateTimeImmutable('2026-01-15'), new DateTimeImmutable('2026-04-16'), new WorkCalendar);

        self::assertSame('2026-02-28', $endOfMonth?->dateString());
        self::assertSame('2025-02-28', $leapDay?->dateString());
        self::assertSame('2026-05-15', $skipped?->dateString());
    }

    public function test_end_date_inclusively_ends_the_series_when_no_future_occurrence_is_eligible(): void
    {
        $rule = new RecurrenceRule(
            RecurrenceBasis::Fixed,
            RecurrenceFrequency::Weekly,
            weekdays: [1],
            endsOn: new DateTimeImmutable('2026-10-05'),
        );

        $next = (new RecurrenceCalculator)->next(
            $rule,
            new LogicalOccurrence(new DateTimeImmutable('2026-10-05')),
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-05'),
            new WorkCalendar,
        );

        self::assertNull($next);
    }

    public function test_lifecycle_preserves_the_open_task_when_removing_rule_and_completes_when_ending(): void
    {
        $lifecycle = new RecurrenceLifecycle;
        $rule = new RecurrenceRule(
            RecurrenceBasis::Fixed,
            RecurrenceFrequency::Weekly,
            weekdays: [1],
            endsOn: new DateTimeImmutable('2026-10-05'),
        );

        $ended = $lifecycle->afterCompletion(
            $rule,
            new LogicalOccurrence(new DateTimeImmutable('2026-10-05')),
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-05'),
            new WorkCalendar,
        );

        self::assertTrue($ended->taskCompleted);
        self::assertNull($ended->rule);
        self::assertFalse($lifecycle->removeRule()->taskCompleted);
        self::assertTrue($lifecycle->completeForever()->taskCompleted);
    }

    public function test_fixed_rule_keeps_its_logical_date_when_calendar_marks_it_non_working_after_a_multi_day_occurrence(): void
    {
        $calendar = new WorkCalendar(exceptions: ['2026-10-05' => 'NON_WORKING']);
        $rule = new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Weekly, weekdays: [1]);

        $next = (new RecurrenceCalculator)->next(
            $rule,
            new LogicalOccurrence(new DateTimeImmutable('2026-09-28')),
            new DateTimeImmutable('2026-09-29'),
            new DateTimeImmutable('2026-10-02'),
            new DateTimeImmutable('2026-10-02'),
            $calendar,
        );

        self::assertSame('2026-10-05', $next?->dateString());
        self::assertFalse($calendar->isWorkDay($next?->date ?? throw new \LogicException('Ocorrência ausente.')));
    }
}
