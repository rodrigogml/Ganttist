<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

final readonly class TaskProjectionInput
{
    public function __construct(
        public string $id,
        public ?DateTimeImmutable $start,
        public ?DateTimeImmutable $deadline,
        public bool $completed = false,
        public ?DateTimeImmutable $completionDate = null,
    ) {}
}
