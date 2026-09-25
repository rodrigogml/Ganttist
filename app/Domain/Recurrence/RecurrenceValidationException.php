<?php

declare(strict_types=1);

namespace App\Domain\Recurrence;

use InvalidArgumentException;

final class RecurrenceValidationException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly ?string $token = null)
    {
        parent::__construct($message);
    }
}
