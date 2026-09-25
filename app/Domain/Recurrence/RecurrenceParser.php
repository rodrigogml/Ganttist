<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use DateTimeImmutable;

final class RecurrenceParser
{
    /** @var array<string, int> */
    private const WEEKDAYS = [
        'segunda' => 1, 'segunda-feira' => 1,
        'terca' => 2, 'terca-feira' => 2,
        'quarta' => 3, 'quarta-feira' => 3,
        'quinta' => 4, 'quinta-feira' => 4,
        'sexta' => 5, 'sexta-feira' => 5,
        'sabado' => 6, 'domingo' => 7,
    ];

    /** @var array<string, int> */
    private const MONTHS = [
        'janeiro' => 1, 'jan' => 1, 'fevereiro' => 2, 'fev' => 2, 'marco' => 3, 'mar' => 3,
        'abril' => 4, 'abr' => 4, 'maio' => 5, 'mai' => 5, 'junho' => 6, 'jun' => 6,
        'julho' => 7, 'jul' => 7, 'agosto' => 8, 'ago' => 8, 'setembro' => 9, 'set' => 9,
        'outubro' => 10, 'out' => 10, 'novembro' => 11, 'nov' => 11, 'dezembro' => 12, 'dez' => 12,
    ];

    /** @var array<string, int> */
    private const ORDINALS = ['primeira' => 1, 'segunda' => 2, 'terceira' => 3, 'quarta' => 4, 'quinta' => 5, 'ultima' => -1];

    public function parse(string $expression, ?DateTimeImmutable $referenceDate = null): RecurrenceRule
    {
        $normalized = $this->normalize($expression);
        if ($normalized === '') {
            throw new RecurrenceValidationException('Informe uma expressão de recorrência.', $expression);
        }
        [$body, $startsOn, $endsOn] = $this->extractLimits($normalized, $referenceDate ?? new DateTimeImmutable('today'));

        if (preg_match('/^a cada (\\d+) (dia|dias|semana|semanas|mes|meses|ano|anos)$/', $body, $match) === 1) {
            $unit = match ($match[2]) {
                'dia', 'dias' => RecurrenceIntervalUnit::Workdays,
                'semana', 'semanas' => RecurrenceIntervalUnit::Weeks,
                'mes', 'meses' => RecurrenceIntervalUnit::Months,
                'ano', 'anos' => RecurrenceIntervalUnit::Years,
            };

            return new RecurrenceRule(
                RecurrenceBasis::Interval,
                match ($unit) {
                    RecurrenceIntervalUnit::Workdays => RecurrenceFrequency::Daily,
                    RecurrenceIntervalUnit::Weeks => RecurrenceFrequency::Weekly,
                    RecurrenceIntervalUnit::Months => RecurrenceFrequency::Monthly,
                    RecurrenceIntervalUnit::Years => RecurrenceFrequency::Yearly,
                },
                (int) $match[1],
                $unit,
                startsOn: $startsOn,
                endsOn: $endsOn,
            );
        }

        if (in_array($body, ['todo dia', 'todos os dias'], true)) {
            return new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Daily, startsOn: $startsOn, endsOn: $endsOn);
        }
        if (in_array($body, ['toda semana', 'todas as semanas'], true)) {
            return new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Weekly, startsOn: $startsOn, endsOn: $endsOn);
        }
        if (in_array($body, ['todo mes', 'todos os meses'], true)) {
            return new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Monthly, startsOn: $startsOn, endsOn: $endsOn);
        }
        if (in_array($body, ['todo ano', 'todos os anos'], true)) {
            return new RecurrenceRule(RecurrenceBasis::Fixed, RecurrenceFrequency::Yearly, startsOn: $startsOn, endsOn: $endsOn);
        }
        if (preg_match('/^todo (primeiro|ultimo) dia util$/', $body, $match) === 1) {
            return new RecurrenceRule(
                RecurrenceBasis::Fixed,
                RecurrenceFrequency::Monthly,
                workdayPosition: $match[1] === 'primeiro' ? 'first' : 'last',
                startsOn: $startsOn,
                endsOn: $endsOn,
            );
        }
        if (preg_match('/^toda (primeira|segunda|terceira|quarta|quinta|ultima) ([a-z-]+) do mes$/', $body, $match) === 1) {
            return new RecurrenceRule(
                RecurrenceBasis::Fixed,
                RecurrenceFrequency::Monthly,
                monthlyOrdinal: self::ORDINALS[$match[1]],
                monthlyOrdinalWeekday: $this->weekday($match[2]),
                startsOn: $startsOn,
                endsOn: $endsOn,
            );
        }
        if (preg_match('/^todo dia (\\d{1,2}(?:,\\s*\\d{1,2})*)$/', $body, $match) === 1) {
            return new RecurrenceRule(
                RecurrenceBasis::Fixed,
                RecurrenceFrequency::Monthly,
                monthDays: $this->integerList($match[1]),
                startsOn: $startsOn,
                endsOn: $endsOn,
            );
        }
        if (preg_match('/^toda (.+)$/', $body, $match) === 1) {
            $weekdays = array_map(fn (string $value): int => $this->weekday(trim($value)), explode(',', $match[1]));

            return new RecurrenceRule(
                RecurrenceBasis::Fixed,
                RecurrenceFrequency::Weekly,
                weekdays: array_values(array_unique($weekdays)),
                startsOn: $startsOn,
                endsOn: $endsOn,
            );
        }

        throw new RecurrenceValidationException('Expressão de recorrência não suportada.', $expression);
    }

    /** @return array{string, ?DateTimeImmutable, ?DateTimeImmutable} */
    private function extractLimits(string $expression, DateTimeImmutable $referenceDate): array
    {
        $startsOn = null;
        $endsOn = null;
        if (preg_match('/^(.*) comecando (.+) ate (.+)$/', $expression, $match) === 1) {
            $expression = trim($match[1]);
            $startsOn = $this->parseDate(trim($match[2]), $referenceDate);
            $endsOn = $this->parseDate(trim($match[3]), $referenceDate);
        } elseif (preg_match('/^(.*) comecando (.+)$/', $expression, $match) === 1) {
            $expression = trim($match[1]);
            $startsOn = $this->parseDate(trim($match[2]), $referenceDate);
        } elseif (preg_match('/^(.*) ate (.+)$/', $expression, $match) === 1) {
            $expression = trim($match[1]);
            $endsOn = $this->parseDate(trim($match[2]), $referenceDate);
        }

        return [$expression, $startsOn, $endsOn];
    }

    private function parseDate(string $value, DateTimeImmutable $referenceDate): DateTimeImmutable
    {
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date !== false && $date->format('Y-m-d') === $value) {
                return $date;
            }
        }
        if (preg_match('/^(\\d{1,2}) ([a-z]+)(?: (\\d{4}))?$/', $value, $match) === 1 && isset(self::MONTHS[$match[2]])) {
            $year = isset($match[3]) ? (int) $match[3] : (int) $referenceDate->format('Y');
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-".self::MONTHS[$match[2]]."-{$match[1]}");
            if ($date !== false && (int) $date->format('n') === self::MONTHS[$match[2]] && (int) $date->format('j') === (int) $match[1]) {
                return $date;
            }
        }

        throw new RecurrenceValidationException('Data de início ou término inválida.', $value);
    }

    /** @return array<int, int> */
    private function integerList(string $value): array
    {
        $values = array_values(array_unique(array_map('intval', preg_split('/\\s*,\\s*/', $value))));
        sort($values);

        return $values;
    }

    private function weekday(string $value): int
    {
        return self::WEEKDAYS[$value] ?? throw new RecurrenceValidationException('Dia da semana não suportado.', $value);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return preg_replace('/\\s+/', ' ', $value) ?? '';
    }
}
