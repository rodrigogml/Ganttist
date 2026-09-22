<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TaskDurationResolver
{
    public function __construct(private WorkCalendar $calendar) {}

    public function resolve(?DateTimeImmutable $start, ?DateTimeImmutable $finish, ?int $plannedDurationWorkdays): int
    {
        if ($plannedDurationWorkdays !== null) {
            if ($plannedDurationWorkdays < 1 || $plannedDurationWorkdays > 3650) {
                throw new InvalidArgumentException('A duração planejada deve estar entre 1 e 3650 dias úteis.');
            }

            return $plannedDurationWorkdays;
        }
        if ($start === null || $finish === null || $finish < $start) {
            return 1;
        }

        $normalizedFinish = $this->calendar->onOrBefore($finish);

        return $normalizedFinish < $start ? 1 : $this->calendar->countWorkDays($start, $normalizedFinish);
    }
}
