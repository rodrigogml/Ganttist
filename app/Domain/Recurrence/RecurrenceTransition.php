<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

final readonly class RecurrenceTransition
{
    public function __construct(
        public ?RecurrenceRule $rule,
        public ?LogicalOccurrence $occurrence,
        public bool $taskCompleted,
    ) {}
}
