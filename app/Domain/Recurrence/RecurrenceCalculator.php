<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;

final class RecurrenceCalculator
{
    public function first(RecurrenceRule $rule, DateTimeImmutable $today, WorkCalendar $calendar): ?LogicalOccurrence
    {
        $anchor = $rule->startsOn !== null && $rule->startsOn > $today ? $rule->startsOn : $today;
        if ($rule->basis === RecurrenceBasis::Interval) {
            if ($rule->endsOn !== null && $anchor > $rule->endsOn) {
                return null;
            }

            return new LogicalOccurrence($anchor);
        }

        $cursor = $rule->startsOn ?? $today;
        for ($candidate = $anchor, $guard = 0; $guard < 3700; $candidate = $candidate->modify('+1 day'), $guard++) {
            if ($this->matchesFixed($rule, $cursor, $candidate, $calendar)) {
                if ($rule->endsOn !== null && $candidate > $rule->endsOn) {
                    return null;
                }

                return new LogicalOccurrence($candidate);
            }
        }

        throw new RecurrenceValidationException('Não foi possível calcular a primeira ocorrência fixa.');
    }

    public function next(
        RecurrenceRule $rule,
        LogicalOccurrence $cursor,
        DateTimeImmutable $scheduledDate,
        DateTimeImmutable $completedDate,
        DateTimeImmutable $today,
        WorkCalendar $calendar,
    ): ?LogicalOccurrence {
        $base = $this->maxDate($cursor->date, $scheduledDate, $completedDate);
        $candidate = $rule->basis === RecurrenceBasis::Fixed
            ? $this->nextFixed($rule, $cursor->date, $base, $calendar)
            : $this->nextInterval($rule, $base, $calendar);

        while ($candidate <= $today) {
            $candidate = $rule->basis === RecurrenceBasis::Fixed
                ? $this->nextFixed($rule, $cursor->date, $candidate, $calendar)
                : $this->nextInterval($rule, $candidate, $calendar);
        }
        if ($rule->endsOn !== null && $candidate > $rule->endsOn) {
            return null;
        }

        return new LogicalOccurrence($candidate);
    }

    private function nextFixed(RecurrenceRule $rule, DateTimeImmutable $cursor, DateTimeImmutable $after, WorkCalendar $calendar): DateTimeImmutable
    {
        $minimum = $rule->startsOn !== null && $rule->startsOn > $after ? $rule->startsOn->modify('-1 day') : $after;
        for ($candidate = $minimum->modify('+1 day'), $guard = 0; $guard < 3700; $candidate = $candidate->modify('+1 day'), $guard++) {
            if ($this->matchesFixed($rule, $cursor, $candidate, $calendar)) {
                return $candidate;
            }
        }

        throw new RecurrenceValidationException('Não foi possível calcular a próxima ocorrência fixa.');
    }

    private function nextInterval(RecurrenceRule $rule, DateTimeImmutable $base, WorkCalendar $calendar): DateTimeImmutable
    {
        return match ($rule->intervalUnit) {
            RecurrenceIntervalUnit::Workdays => $calendar->addWorkDays($base, $rule->interval),
            RecurrenceIntervalUnit::Weeks => $base->modify("+{$rule->interval} weeks"),
            RecurrenceIntervalUnit::Months => $this->addMonths($base, $rule->interval),
            RecurrenceIntervalUnit::Years => $this->addYears($base, $rule->interval),
            null => throw new RecurrenceValidationException('Unidade de intervalo ausente.'),
        };
    }

    private function matchesFixed(RecurrenceRule $rule, DateTimeImmutable $cursor, DateTimeImmutable $candidate, WorkCalendar $calendar): bool
    {
        if ($rule->workdayPosition !== null) {
            $boundary = $rule->workdayPosition === 'first'
                ? $calendar->onOrAfter($candidate->modify('first day of this month'))
                : $calendar->onOrBefore($candidate->modify('last day of this month'));

            return $candidate->format('Y-m-d') === $boundary->format('Y-m-d');
        }
        if ($rule->monthlyOrdinal !== null) {
            return (int) $candidate->format('N') === $rule->monthlyOrdinalWeekday
                && $this->ordinalInMonth($candidate) === $rule->monthlyOrdinal;
        }
        if ($rule->monthDays !== []) {
            return in_array((int) $candidate->format('j'), $rule->monthDays, true);
        }
        if ($rule->annualDates !== []) {
            foreach ($rule->annualDates as $date) {
                if ((int) $candidate->format('n') === $date['month'] && (int) $candidate->format('j') === min($date['day'], $this->daysInMonth((int) $candidate->format('Y'), $date['month']))) {
                    return true;
                }
            }

            return false;
        }
        if ($rule->weekdays !== []) {
            return in_array((int) $candidate->format('N'), $rule->weekdays, true);
        }

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => true,
            RecurrenceFrequency::Weekly => $candidate->format('N') === $cursor->format('N'),
            RecurrenceFrequency::Monthly => (int) $candidate->format('j') === min((int) $cursor->format('j'), $this->daysInMonth((int) $candidate->format('Y'), (int) $candidate->format('n'))),
            RecurrenceFrequency::Yearly => (int) $candidate->format('n') === (int) $cursor->format('n') && (int) $candidate->format('j') === min((int) $cursor->format('j'), $this->daysInMonth((int) $candidate->format('Y'), (int) $cursor->format('n'))),
        };
    }

    private function ordinalInMonth(DateTimeImmutable $date): int
    {
        $nextWeek = $date->modify('+7 days');
        if ($nextWeek->format('m') !== $date->format('m')) {
            return -1;
        }

        return intdiv((int) $date->format('j') - 1, 7) + 1;
    }

    private function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $monthStart = $date->modify('first day of this month')->modify("+{$months} months");
        $day = min((int) $date->format('j'), $this->daysInMonth((int) $monthStart->format('Y'), (int) $monthStart->format('n')));

        return $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), $day);
    }

    private function addYears(DateTimeImmutable $date, int $years): DateTimeImmutable
    {
        $year = (int) $date->format('Y') + $years;
        $month = (int) $date->format('n');

        return $date->setDate($year, $month, min((int) $date->format('j'), $this->daysInMonth($year, $month)));
    }

    private function daysInMonth(int $year, int $month): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month')->format('j');
    }

    private function maxDate(DateTimeImmutable ...$dates): DateTimeImmutable
    {
        usort($dates, static fn (DateTimeImmutable $left, DateTimeImmutable $right): int => $left <=> $right);

        return $dates[array_key_last($dates)];
    }
}
