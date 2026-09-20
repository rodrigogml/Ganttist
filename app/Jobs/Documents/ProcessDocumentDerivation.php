<?php

declare(strict_types=1);

namespace App\Jobs\Documents;

use App\Contracts\Documents\DocumentProcessorContract;
use App\Exceptions\PermanentDocumentProcessingException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessDocumentDerivation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120];

    public function __construct(public readonly string $runId)
    {
        $this->onConnection('documents')->onQueue('documents');
    }

    public function handle(DocumentProcessorContract $processor): void
    {
        try {
            $processor->process($this->runId);
        } catch (PermanentDocumentProcessingException) {
            return;
        }
    }
}
