<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

enum RecurrenceBasis: string
{
    case Fixed = 'fixed';
    case Interval = 'interval';
}
