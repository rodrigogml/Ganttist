<?php

declare(strict_types=1);

namespace App\Contracts;

interface IncrementalTodoistGateway
{
    /** @return array<string, mixed> */
    public function incrementalSync(string $accessToken, string $syncToken): array;
}
