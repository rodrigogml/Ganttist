<?php

namespace Tests\Unit;

use App\Domain\Recurrence\RecurrenceValidationException;
use App\Domain\Recurrence\SnoozeTargetResolver;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SnoozeTargetResolverTest extends TestCase
{
    public function test_quick_options_and_manual_dates_are_normalized_to_project_workdays(): void
    {
        $resolver = new SnoozeTargetResolver(new WorkCalendar(exceptions: ['2026-09-28' => 'NON_WORKING']));
        $friday = new DateTimeImmutable('2026-09-25');

        self::assertSame('2026-09-29', $resolver->resolve('nextWorkday', $friday)->format('Y-m-d'));
        self::assertSame('2026-09-29', $resolver->resolve('nextWeek', $friday)->format('Y-m-d'));
        self::assertSame('2026-10-01', $resolver->resolve('workdays', $friday, 3)->format('Y-m-d'));
        self::assertSame('2026-10-07', $resolver->resolve('workdays', $friday, 7)->format('Y-m-d'));
        self::assertSame('2026-09-29', $resolver->resolve('date', $friday, targetDate: new DateTimeImmutable('2026-09-27'))->format('Y-m-d'));
    }

    public function test_invalid_workday_count_is_rejected(): void
    {
        $this->expectException(RecurrenceValidationException::class);
        (new SnoozeTargetResolver(new WorkCalendar))->resolve('workdays', new DateTimeImmutable('2026-09-25'), 2);
    }
}
