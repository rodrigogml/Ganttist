<?php

namespace Tests\Unit;

use App\Domain\Recurrence\RecurrenceBasis;
use App\Domain\Recurrence\RecurrenceFormatter;
use App\Domain\Recurrence\RecurrenceFrequency;
use App\Domain\Recurrence\RecurrenceIntervalUnit;
use App\Domain\Recurrence\RecurrenceParser;
use App\Domain\Recurrence\RecurrenceRule;
use App\Domain\Recurrence\RecurrenceValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecurrenceParserTest extends TestCase
{
    public function test_parser_supports_published_fixed_patterns_and_canonical_formatting(): void
    {
        $parser = new RecurrenceParser;
        $formatter = new RecurrenceFormatter;

        $weekdays = $parser->parse('Toda segunda, quarta, sexta');
        self::assertSame(RecurrenceBasis::Fixed, $weekdays->basis);
        self::assertSame(RecurrenceFrequency::Weekly, $weekdays->frequency);
        self::assertSame([1, 3, 5], $weekdays->weekdays);
        self::assertSame('toda segunda, quarta, sexta', $formatter->format($weekdays));

        self::assertSame('todo dia 5, 15, 25', $formatter->format($parser->parse('todo dia 5,15,25')));
        self::assertSame('todo último dia útil', $formatter->format($parser->parse('todo último dia útil')));
        self::assertSame('toda primeira segunda do mês', $formatter->format($parser->parse('toda primeira segunda do mês')));
    }

    public function test_parser_distinguishes_workday_and_calendar_intervals_and_limits(): void
    {
        $parser = new RecurrenceParser;
        $formatter = new RecurrenceFormatter;

        $workdays = $parser->parse('a cada 4 dias');
        self::assertSame(RecurrenceBasis::Interval, $workdays->basis);
        self::assertSame(RecurrenceIntervalUnit::Workdays, $workdays->intervalUnit);
        self::assertSame('a cada 4 dias', $formatter->format($workdays));

        self::assertSame(RecurrenceIntervalUnit::Weeks, $parser->parse('a cada 3 semanas')->intervalUnit);
        self::assertSame(RecurrenceIntervalUnit::Months, $parser->parse('a cada 2 meses')->intervalUnit);
        self::assertSame(RecurrenceIntervalUnit::Years, $parser->parse('a cada 1 ano')->intervalUnit);

        $limited = $parser->parse('toda segunda começando 5 out 2026 até 31 dez 2026', new DateTimeImmutable('2026-09-24'));
        self::assertSame('2026-10-05', $limited->startsOn?->format('Y-m-d'));
        self::assertSame('2026-12-31', $limited->endsOn?->format('Y-m-d'));
        self::assertSame('toda segunda começando 2026-10-05 até 2026-12-31', $formatter->format($limited));
    }

    public function test_structured_rule_roundtrips_without_a_second_source_of_truth(): void
    {
        $parser = new RecurrenceParser;
        $rule = $parser->parse('toda segunda, sexta começando 2026-10-05 até 2026-12-31');

        $fromEditor = RecurrenceRule::fromArray($rule->toArray());

        self::assertSame($rule->toArray(), $fromEditor->toArray());
        self::assertSame((new RecurrenceFormatter)->format($rule), (new RecurrenceFormatter)->format($fromEditor));
    }

    public function test_parser_reports_the_invalid_token_without_mutating_a_rule(): void
    {
        $parser = new RecurrenceParser;

        try {
            $parser->parse('toda oitava-feira');
            self::fail('Uma expressão fora da gramática deveria ser recusada.');
        } catch (RecurrenceValidationException $exception) {
            self::assertSame('oitava-feira', $exception->token);
            self::assertSame('Dia da semana não suportado.', $exception->getMessage());
        }

        $this->expectException(RecurrenceValidationException::class);
        RecurrenceRule::fromArray([
            'version' => 1,
            'basis' => 'interval',
            'frequency' => 'daily',
            'interval' => 0,
            'intervalUnit' => 'workdays',
        ]);
    }
}
