<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

enum RecurrenceIntervalUnit: string
{
    case Workdays = 'workdays';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';
}
