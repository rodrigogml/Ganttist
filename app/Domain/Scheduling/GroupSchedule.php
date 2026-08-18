<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

final readonly class GroupSchedule
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $finish) {}
}
