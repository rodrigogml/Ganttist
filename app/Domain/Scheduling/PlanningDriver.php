<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

enum PlanningDriver: string
{
    case Start = 'start';
    case Finish = 'finish';
    case Duration = 'duration';
}
