<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TaskPlanningPatch
{
    public function __construct(
        public bool $hasStart = false,
        public ?DateTimeImmutable $start = null,
        public bool $hasFinish = false,
        public ?DateTimeImmutable $finish = null,
        public bool $hasDurationWorkdays = false,
        public ?int $durationWorkdays = null,
    ) {
        if (! $hasStart && $start !== null) {
            throw new InvalidArgumentException('Um início só pode ser informado em um patch que o altere.');
        }
        if (! $hasFinish && $finish !== null) {
            throw new InvalidArgumentException('Um fim só pode ser informado em um patch que o altere.');
        }
        if (! $hasDurationWorkdays && $durationWorkdays !== null) {
            throw new InvalidArgumentException('Uma duração só pode ser informada em um patch que a altere.');
        }
    }

    public function isEmpty(): bool
    {
        return ! $this->hasStart && ! $this->hasFinish && ! $this->hasDurationWorkdays;
    }

    public function clearsAnyValue(): bool
    {
        return ($this->hasStart && $this->start === null)
            || ($this->hasFinish && $this->finish === null)
            || ($this->hasDurationWorkdays && $this->durationWorkdays === null);
    }
}
