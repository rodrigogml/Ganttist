<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use DateTimeImmutable;

final readonly class RecurrenceRule
{
    /**
     * @param  array<int, int>  $weekdays  ISO weekdays, 1 (segunda) até 7 (domingo)
     * @param  array<int, int>  $monthDays  dias do mês, 1 até 31
     * @param  array<int, array{month: int, day: int}>  $annualDates
     */
    public function __construct(
        public RecurrenceBasis $basis,
        public RecurrenceFrequency $frequency,
        public int $interval = 1,
        public ?RecurrenceIntervalUnit $intervalUnit = null,
        public array $weekdays = [],
        public array $monthDays = [],
        public array $annualDates = [],
        public ?int $monthlyOrdinal = null,
        public ?int $monthlyOrdinalWeekday = null,
        public ?string $workdayPosition = null,
        public ?DateTimeImmutable $startsOn = null,
        public ?DateTimeImmutable $endsOn = null,
        public int $version = 1,
    ) {
        if ($this->version !== 1) {
            throw new RecurrenceValidationException('Versão de recorrência não suportada.');
        }
        if ($this->interval < 1) {
            throw new RecurrenceValidationException('O intervalo precisa ser maior que zero.');
        }
        if ($this->endsOn !== null && $this->startsOn !== null && $this->endsOn < $this->startsOn) {
            throw new RecurrenceValidationException('O término da recorrência não pode anteceder o início.');
        }
        if ($this->basis === RecurrenceBasis::Interval && $this->intervalUnit === null) {
            throw new RecurrenceValidationException('Uma recorrência por intervalo precisa informar sua unidade.');
        }
        if ($this->basis === RecurrenceBasis::Fixed && $this->intervalUnit !== null) {
            throw new RecurrenceValidationException('Uma recorrência fixa não aceita unidade de intervalo.');
        }
        $this->validateSelectors();
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $startsOn = self::date($data['startsOn'] ?? null, 'início');
        $endsOn = self::date($data['endsOn'] ?? null, 'término');
        $annualDates = array_map(static fn (array $date): array => [
            'month' => (int) ($date['month'] ?? 0),
            'day' => (int) ($date['day'] ?? 0),
        ], $data['annualDates'] ?? []);

        return new self(
            RecurrenceBasis::from((string) ($data['basis'] ?? '')),
            RecurrenceFrequency::from((string) ($data['frequency'] ?? '')),
            (int) ($data['interval'] ?? 1),
            isset($data['intervalUnit']) ? RecurrenceIntervalUnit::from((string) $data['intervalUnit']) : null,
            array_map('intval', $data['weekdays'] ?? []),
            array_map('intval', $data['monthDays'] ?? []),
            $annualDates,
            isset($data['monthlyOrdinal']) ? (int) $data['monthlyOrdinal'] : null,
            isset($data['monthlyOrdinalWeekday']) ? (int) $data['monthlyOrdinalWeekday'] : null,
            isset($data['workdayPosition']) ? (string) $data['workdayPosition'] : null,
            $startsOn,
            $endsOn,
            (int) ($data['version'] ?? 1),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'basis' => $this->basis->value,
            'frequency' => $this->frequency->value,
            'interval' => $this->interval,
            'intervalUnit' => $this->intervalUnit?->value,
            'weekdays' => $this->weekdays,
            'monthDays' => $this->monthDays,
            'annualDates' => $this->annualDates,
            'monthlyOrdinal' => $this->monthlyOrdinal,
            'monthlyOrdinalWeekday' => $this->monthlyOrdinalWeekday,
            'workdayPosition' => $this->workdayPosition,
            'startsOn' => $this->startsOn?->format('Y-m-d'),
            'endsOn' => $this->endsOn?->format('Y-m-d'),
        ];
    }

    private function validateSelectors(): void
    {
        foreach ($this->weekdays as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw new RecurrenceValidationException('Dia da semana inválido.');
            }
        }
        foreach ($this->monthDays as $day) {
            if ($day < 1 || $day > 31) {
                throw new RecurrenceValidationException('Dia do mês inválido.');
            }
        }
        foreach ($this->annualDates as $date) {
            if ($date['month'] < 1 || $date['month'] > 12 || $date['day'] < 1 || $date['day'] > 31) {
                throw new RecurrenceValidationException('Data anual inválida.');
            }
        }
        if ($this->monthlyOrdinal !== null && ! in_array($this->monthlyOrdinal, [1, 2, 3, 4, 5, -1], true)) {
            throw new RecurrenceValidationException('Ordinal mensal inválido.');
        }
        if ($this->monthlyOrdinalWeekday !== null && ($this->monthlyOrdinalWeekday < 1 || $this->monthlyOrdinalWeekday > 7)) {
            throw new RecurrenceValidationException('Dia da semana do ordinal mensal inválido.');
        }
        if (($this->monthlyOrdinal === null) !== ($this->monthlyOrdinalWeekday === null)) {
            throw new RecurrenceValidationException('Ordinal mensal precisa informar posição e dia da semana.');
        }
        if ($this->workdayPosition !== null && ! in_array($this->workdayPosition, ['first', 'last'], true)) {
            throw new RecurrenceValidationException('Posição de dia útil inválida.');
        }
    }

    private static function date(mixed $value, string $label): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
            throw new RecurrenceValidationException("Data de {$label} inválida.");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RecurrenceValidationException("Data de {$label} inválida.");
        }

        return $date;
    }
}
