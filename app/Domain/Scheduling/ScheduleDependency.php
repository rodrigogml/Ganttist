<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

final readonly class ScheduleDependency
{
    public function __construct(
        public string $predecessorKind,
        public string $predecessorId,
        public string $successorKind,
        public string $successorId,
        public string $type,
    ) {
        if (! in_array($predecessorKind, ['task', 'section'], true) || ! in_array($successorKind, ['task', 'section'], true)) {
            throw new InvalidArgumentException('Tipo de ponta de dependência inválido.');
        }
        if ($predecessorKind === $successorKind && $predecessorId === $successorId) {
            throw new InvalidArgumentException('Um item não pode depender de si mesmo.');
        }
        if (! in_array($type, ['FS', 'SS', 'FF', 'SF'], true)) {
            throw new InvalidArgumentException('Tipo de dependência inválido.');
        }
    }
}
