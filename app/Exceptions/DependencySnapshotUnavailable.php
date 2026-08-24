<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DependencySnapshotUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Atualize o workspace antes de criar uma dependência.');
    }
}
