<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

final readonly class TaskProjection
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $consideredStart,
        public DateTimeImmutable $consideredDeadline,
        public ?DateTimeImmutable $unlockDate,
        public ?DateTimeImmutable $earliestStart,
        public DateTimeImmutable $effectiveCompletionDate,
        public ProjectedTaskStatus $status,
        public int $resolvedDurationWorkdays = 1,
        public ScheduleConstraintState $scheduleConstraintState = ScheduleConstraintState::Satisfied,
        public ?string $scheduleConstraintReason = null,
    ) {}
}
