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
        public ?DateTimeImmutable $deadline = null,
        public ?int $plannedDurationWorkdays = null,
        public bool $participatesInFiniteNetwork = true,
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
        ?int $plannedDurationWorkdays = null,
        bool $participatesInFiniteNetwork = true,
    ): self {
        $duration = (new TaskDurationResolver($calendar))->resolve($start, $deadline, $plannedDurationWorkdays);

        return new self($id, $title, $start, $duration, $completed, $effectiveCompletionDate, $parentId, $deadline, $plannedDurationWorkdays, $participatesInFiniteNetwork);
    }

    public function anchoredStart(WorkCalendar $calendar): ?DateTimeImmutable
    {
        if ($this->start !== null) {
            return $calendar->onOrAfter($this->start);
        }
        if ($this->deadline !== null && $this->plannedDurationWorkdays !== null) {
            return $calendar->subtractWorkDays($this->deadline, $this->duration - 1);
        }

        return null;
    }

    public function finish(WorkCalendar $calendar, ?DateTimeImmutable $fallbackStart = null): DateTimeImmutable
    {
        if ($this->completed && $this->effectiveCompletionDate !== null) {
            return $this->effectiveCompletionDate;
        }
        $start = $this->completed && $this->effectiveCompletionDate
            ? $this->effectiveCompletionDate
            : ($this->anchoredStart($calendar) ?? $fallbackStart);

        return $calendar->addWorkDays($start ?? throw new \LogicException('Tarefa sem data virtual.'), $this->duration - 1);
    }

    public function withStart(DateTimeImmutable $start): self
    {
        return new self($this->id, $this->title, $start, $this->duration, $this->completed, $this->effectiveCompletionDate, $this->parentId, $this->deadline, $this->plannedDurationWorkdays, $this->participatesInFiniteNetwork);
    }
}
