<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

enum ProjectedTaskStatus: string
{
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Scheduled = 'scheduled';
    case Late = 'late';
    case InProgress = 'in_progress';
    case Opened = 'opened';
}
