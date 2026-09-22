<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

enum ScheduleConstraintState: string
{
    case Satisfied = 'satisfied';
    case Violated = 'violated';
}
