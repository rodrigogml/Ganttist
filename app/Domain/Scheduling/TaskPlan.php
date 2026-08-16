<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

final readonly class TaskPlan
{
    public function __construct(
        public string $id,
        public string $title,
        public ?DateTimeImmutable $start,
        public int $duration = 1,
        public bool $completed = false,
        public ?DateTimeImmutable $effectiveCompletionDate = null,
        public ?string $parentId = null,
    ) {}

    public function finish(WorkCalendar $calendar, ?DateTimeImmutable $fallbackStart = null): DateTimeImmutable
    {
        $start = $this->completed && $this->effectiveCompletionDate
            ? $this->effectiveCompletionDate
            : ($this->start ?? $fallbackStart);

        return $calendar->addWorkDays($start ?? throw new \LogicException('Tarefa sem data virtual.'), $this->duration - 1);
    }

    public function withStart(DateTimeImmutable $start): self
    {
        return new self($this->id, $this->title, $start, $this->duration, $this->completed, $this->effectiveCompletionDate, $this->parentId);
    }
}
