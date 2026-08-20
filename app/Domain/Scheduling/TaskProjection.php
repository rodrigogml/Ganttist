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
        public DateTimeImmutable $effectiveCompletionDate,
        public ProjectedTaskStatus $status,
    ) {}
}
