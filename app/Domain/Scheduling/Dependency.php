<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

final readonly class Dependency
{
    public function __construct(public string $predecessorId, public string $successorId, public string $type = 'FS')
    {
        if ($predecessorId === $successorId) {
            throw new InvalidArgumentException('Uma tarefa não pode depender de si mesma.');
        }
        if (! in_array($type, ['FS', 'SS', 'FF', 'SF'], true)) {
            throw new InvalidArgumentException("Tipo de dependência inválido: {$type}.");
        }
    }
}
