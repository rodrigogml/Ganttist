<?php

namespace Tests\Unit;

use App\Contracts\Documents\DocumentProcessorContract;
use App\Exceptions\PermanentDocumentProcessingException;
use App\Jobs\Documents\ProcessDocumentDerivation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessDocumentDerivationTest extends TestCase
{
    public function test_transient_failures_are_rethrown_for_the_queue_to_retry(): void
    {
        $processor = new class implements DocumentProcessorContract
        {
            public function process(string $runId): void
            {
                throw new RuntimeException('temporary');
            }
        };
        $job = new ProcessDocumentDerivation('run');

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120], $job->backoff);
        $this->expectException(RuntimeException::class);
        $job->handle($processor);
    }

    public function test_permanent_failures_finish_without_an_automatic_retry(): void
    {
        $processor = new class implements DocumentProcessorContract
        {
            public function process(string $runId): void
            {
                throw new PermanentDocumentProcessingException('invalid');
            }
        };

        (new ProcessDocumentDerivation('run'))->handle($processor);

        $this->addToAssertionCount(1);
    }
}
