<?php

namespace App\Jobs\Documents;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class PurgeProjectDocumentFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, string> $storageDisks */
    public function __construct(public readonly string $projectId, public readonly array $storageDisks)
    {
        $this->onConnection('documents')->onQueue('documents');
    }

    public function handle(): void
    {
        foreach (array_unique($this->storageDisks) as $disk) {
            Storage::disk($disk)->deleteDirectory("projects/{$this->projectId}/documents");
        }
    }
}
