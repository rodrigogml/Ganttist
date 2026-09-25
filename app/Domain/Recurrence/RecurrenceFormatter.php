<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

final class RecurrenceFormatter
{
    private const WEEKDAYS = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];

    private const ORDINALS = [1 => 'primeira', 2 => 'segunda', 3 => 'terceira', 4 => 'quarta', 5 => 'quinta', -1 => 'última'];

    public function format(RecurrenceRule $rule): string
    {
        $text = $rule->basis === RecurrenceBasis::Interval
            ? $this->formatInterval($rule)
            : $this->formatFixed($rule);

        if ($rule->startsOn !== null) {
            $text .= ' começando '.$rule->startsOn->format('Y-m-d');
        }
        if ($rule->endsOn !== null) {
            $text .= ' até '.$rule->endsOn->format('Y-m-d');
        }

        return $text;
    }

    private function formatInterval(RecurrenceRule $rule): string
    {
        $unit = match ($rule->intervalUnit) {
            RecurrenceIntervalUnit::Workdays => $rule->interval === 1 ? 'dia' : 'dias',
            RecurrenceIntervalUnit::Weeks => $rule->interval === 1 ? 'semana' : 'semanas',
            RecurrenceIntervalUnit::Months => $rule->interval === 1 ? 'mês' : 'meses',
            RecurrenceIntervalUnit::Years => $rule->interval === 1 ? 'ano' : 'anos',
            null => throw new RecurrenceValidationException('Unidade de intervalo ausente.'),
        };

        return "a cada {$rule->interval} {$unit}";
    }

    private function formatFixed(RecurrenceRule $rule): string
    {
        if ($rule->workdayPosition !== null) {
            return 'todo '.($rule->workdayPosition === 'first' ? 'primeiro' : 'último').' dia útil';
        }
        if ($rule->monthlyOrdinal !== null) {
            return 'toda '.self::ORDINALS[$rule->monthlyOrdinal].' '.self::WEEKDAYS[$rule->monthlyOrdinalWeekday].' do mês';
        }
        if ($rule->monthDays !== []) {
            return 'todo dia '.implode(', ', $rule->monthDays);
        }
        if ($rule->weekdays !== []) {
            return 'toda '.implode(', ', array_map(fn (int $weekday): string => self::WEEKDAYS[$weekday], $rule->weekdays));
        }

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => 'todo dia',
            RecurrenceFrequency::Weekly => 'toda semana',
            RecurrenceFrequency::Monthly => 'todo mês',
            RecurrenceFrequency::Yearly => 'todo ano',
        };
    }
}
