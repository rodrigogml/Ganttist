<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;

final readonly class RecurrenceLifecycle
{
    public function __construct(private RecurrenceCalculator $calculator = new RecurrenceCalculator) {}

    public function afterCompletion(
        RecurrenceRule $rule,
        LogicalOccurrence $cursor,
        DateTimeImmutable $scheduledDate,
        DateTimeImmutable $completedDate,
        DateTimeImmutable $today,
        WorkCalendar $calendar,
    ): RecurrenceTransition {
        $next = $this->calculator->next($rule, $cursor, $scheduledDate, $completedDate, $today, $calendar);

        return new RecurrenceTransition($next === null ? null : $rule, $next, $next === null);
    }

    public function removeRule(): RecurrenceTransition
    {
        return new RecurrenceTransition(null, null, false);
    }

    public function completeForever(): RecurrenceTransition
    {
        return new RecurrenceTransition(null, null, true);
    }
}
