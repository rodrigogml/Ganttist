<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

final class WorkCalendar
{
    /** @param array<int, bool> $weekdays ISO weekday (1=Monday ... 7=Sunday) */
    public function __construct(
        private readonly array $weekdays = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false, 7 => false],
        /** @var array<string, 'WORKING'|'NON_WORKING'> */
        private readonly array $exceptions = [],
    ) {
        if (! in_array(true, $this->weekdays, true) && ! in_array('WORKING', $this->exceptions, true)) {
            throw new InvalidArgumentException('O calendário precisa possuir ao menos um dia útil.');
        }
    }

    public function isWorkDay(DateTimeImmutable $date): bool
    {
        $key = $date->format('Y-m-d');
        if (isset($this->exceptions[$key])) {
            return $this->exceptions[$key] === 'WORKING';
        }

        return $this->weekdays[(int) $date->format('N')] ?? false;
    }

    public function nextWorkDay(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->seek($date, 1, false);
    }

    public function previousWorkDay(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->seek($date, -1, false);
    }

    public function onOrAfter(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->isWorkDay($date) ? $date : $this->seek($date, 1, false);
    }

    public function onOrBefore(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->isWorkDay($date) ? $date : $this->seek($date, -1, false);
    }

    public function addWorkDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days < 0) {
            return $this->subtractWorkDays($date, -$days);
        }
        $cursor = $this->onOrAfter($date);
        for ($i = 0; $i < $days; $i++) {
            $cursor = $this->nextWorkDay($cursor);
        }

        return $cursor;
    }

    public function subtractWorkDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        $cursor = $this->onOrBefore($date);
        for ($i = 0; $i < $days; $i++) {
            $cursor = $this->previousWorkDay($cursor);
        }

        return $cursor;
    }

    public function countWorkDays(DateTimeImmutable $start, DateTimeImmutable $finish): int
    {
        if ($finish < $start) {
            throw new InvalidArgumentException('A data final não pode ser anterior à data inicial.');
        }
        $count = 0;
        for ($cursor = $start; $cursor <= $finish; $cursor = $cursor->modify('+1 day')) {
            $count += $this->isWorkDay($cursor) ? 1 : 0;
        }

        return max(1, $count);
    }

    public function operationalToday(DateTimeImmutable $localToday): DateTimeImmutable
    {
        return $this->onOrAfter($localToday);
    }

    private function seek(DateTimeImmutable $date, int $direction, bool $inclusive): DateTimeImmutable
    {
        $cursor = $inclusive ? $date : $date->modify($direction > 0 ? '+1 day' : '-1 day');
        for ($guard = 0; $guard < 3700; $guard++) {
            if ($this->isWorkDay($cursor)) {
                return $cursor;
            }
            $cursor = $cursor->modify($direction > 0 ? '+1 day' : '-1 day');
        }
        throw new InvalidArgumentException('Calendário inválido: nenhum dia útil encontrado.');
    }
}
