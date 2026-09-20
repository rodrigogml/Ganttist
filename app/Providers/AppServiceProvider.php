<?php

namespace App\Providers;

use App\Contracts\Documents\DocumentProcessorContract;
use App\Contracts\Documents\DocumentStorageContract;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\PdfCropProcessor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentStorageContract::class, DocumentStorage::class);
        $this->app->bind(DocumentProcessorContract::class, PdfCropProcessor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
