<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;

final readonly class SnoozeTargetResolver
{
    public function __construct(private WorkCalendar $calendar) {}

    public function resolve(string $option, DateTimeImmutable $today, ?int $workdays = null, ?DateTimeImmutable $targetDate = null): DateTimeImmutable
    {
        return match ($option) {
            'nextWorkday' => $this->calendar->nextWorkDay($today),
            'nextWeek' => $this->calendar->onOrAfter($today->modify('-'.((int) $today->format('N') - 1).' days')->modify('+1 week')),
            'workdays' => $this->resolveWorkdays($today, $workdays),
            'date' => $targetDate === null
                ? throw new RecurrenceValidationException('Informe a data da soneca.')
                : $this->calendar->onOrAfter($targetDate),
            default => throw new RecurrenceValidationException('Opção de soneca inválida.'),
        };
    }

    private function resolveWorkdays(DateTimeImmutable $today, ?int $workdays): DateTimeImmutable
    {
        if (! in_array($workdays, [3, 7], true)) {
            throw new RecurrenceValidationException('A soneca em dias úteis aceita somente 3 ou 7 dias.');
        }

        return $this->calendar->addWorkDays($today, $workdays);
    }
}
