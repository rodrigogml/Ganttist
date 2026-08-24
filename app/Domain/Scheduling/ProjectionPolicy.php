<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

enum ProjectionPolicy: string
{
    case PreserveDuration = 'PRESERVE_DURATION';
    case PreserveDeadline = 'PRESERVE_DEADLINE';

    public static function fromSetting(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::PreserveDuration;
    }
}
