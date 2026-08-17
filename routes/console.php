<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Contracts\TodoistGateway;
use App\Services\TodoistSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('todoist:sync', function (TodoistSyncService $sync, TodoistGateway $gateway): void {
    $result = $sync->syncActiveProjects($gateway);
    $this->info("Projetos sincronizados: {$result['synced']}; falhas: {$result['failed']}.");
})->purpose('Reconcilia projetos ativos com o Todoist');
