<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use DateTimeImmutable;

final readonly class LogicalOccurrence
{
    public function __construct(public DateTimeImmutable $date) {}

    public function dateString(): string
    {
        return $this->date->format('Y-m-d');
    }
}
