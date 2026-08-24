<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TodoistSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApplyProjectAutomations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly string $projectId) {}

    public function handle(TodoistSyncService $sync): void
    {
        $sync->applyProjectAutomations($this->projectId);
    }
}
