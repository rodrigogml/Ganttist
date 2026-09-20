<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

interface DocumentProcessorContract
{
    public function process(string $runId): void;
}
