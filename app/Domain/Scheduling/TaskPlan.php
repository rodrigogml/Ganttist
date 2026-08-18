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

    public static function fromDates(
        string $id,
        string $title,
        ?DateTimeImmutable $start,
        ?DateTimeImmutable $deadline,
        WorkCalendar $calendar,
        bool $completed = false,
        ?DateTimeImmutable $effectiveCompletionDate = null,
        ?string $parentId = null,
    ): self {
        if ($start === null || $deadline === null || $deadline < $start) {
            return new self($id, $title, $start, 1, $completed, $effectiveCompletionDate, $parentId);
        }
        $normalizedDeadline = $calendar->onOrBefore($deadline);
        if ($normalizedDeadline < $start) {
            return new self($id, $title, $start, 1, $completed, $effectiveCompletionDate, $parentId);
        }

        return new self($id, $title, $start, $calendar->countWorkDays($start, $normalizedDeadline), $completed, $effectiveCompletionDate, $parentId);
    }

    public function finish(WorkCalendar $calendar, ?DateTimeImmutable $fallbackStart = null): DateTimeImmutable
    {
        if ($this->completed && $this->effectiveCompletionDate !== null) {
            return $this->effectiveCompletionDate;
        }
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
