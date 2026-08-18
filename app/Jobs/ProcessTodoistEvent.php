<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TodoistSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessTodoistEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly string $eventId) {}

    public function handle(TodoistSyncService $sync): void
    {
        if (! $sync->processEvent($this->eventId)) {
            $this->release(60);
        }
    }
}
