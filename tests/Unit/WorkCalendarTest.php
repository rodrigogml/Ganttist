<?php

namespace Tests\Unit;

use App\Domain\Scheduling\ProjectCalendar;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkCalendarTest extends TestCase
{
    public function test_weekend_and_exception_are_not_counted(): void
    {
        $calendar = new WorkCalendar(exceptions: ['2026-08-21' => 'NON_WORKING']);
        self::assertSame('2026-08-24', $calendar->nextWorkDay(new DateTimeImmutable('2026-08-20'))->format('Y-m-d'));
        self::assertSame(4, $calendar->countWorkDays(new DateTimeImmutable('2026-08-17'), new DateTimeImmutable('2026-08-23')));
    }

    public function test_specific_working_exception_overrides_weekend(): void
    {
        $calendar = new WorkCalendar(exceptions: ['2026-08-22' => 'WORKING']);
        self::assertTrue($calendar->isWorkDay(new DateTimeImmutable('2026-08-22')));
        self::assertSame('2026-08-22', $calendar->nextWorkDay(new DateTimeImmutable('2026-08-21'))->format('Y-m-d'));
    }

    public function test_operational_today_advances_from_sunday(): void
    {
        self::assertSame('2026-08-17', (new WorkCalendar)->operationalToday(new DateTimeImmutable('2026-08-16'))->format('Y-m-d'));
    }

    public function test_project_settings_and_exceptions_build_calendar(): void
    {
        $calendar = ProjectCalendar::fromSettings([
            'monday' => false, 'tuesday' => true, 'wednesday' => true,
            'thursday' => true, 'friday' => true, 'saturday' => false, 'sunday' => false,
        ], [['date' => '2026-08-17', 'type' => 'WORKING']]);

        self::assertTrue($calendar->isWorkDay(new DateTimeImmutable('2026-08-17')));
        self::assertSame('2026-08-18', $calendar->nextWorkDay(new DateTimeImmutable('2026-08-17'))->format('Y-m-d'));
    }
}
