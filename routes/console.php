<?php

use App\Services\TodoistSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('todoist:sync', function (TodoistSyncService $sync): void {
    $result = $sync->syncActiveProjects();
    $this->info("Projetos sincronizados: {$result['synced']}; falhas: {$result['failed']}.");
})->purpose('Reconcilia projetos ativos com o Todoist');

Artisan::command('audit:prune', function (): void {
    $days = max(1, (int) config('ganttist.audit_retention_days', 365));
    $deleted = DB::table('audit_events')->where('occurred_at', '<', now()->subDays($days))->delete();
    $this->info("Eventos de auditoria removidos: {$deleted}.");
})->purpose('Remove eventos de auditoria fora da retenção configurada');

Artisan::command('app:production-readiness', function (): int {
    $checks = [
        'APP_ENV deve ser production.' => config('app.env') === 'production',
        'APP_DEBUG deve ser false.' => config('app.debug') === false,
        'APP_URL deve usar HTTPS.' => str_starts_with((string) config('app.url'), 'https://'),
        'APP_KEY deve estar configurada.' => filled(config('app.key')),
        'SESSION_SECURE_COOKIE deve ser true.' => config('session.secure') === true,
        'O banco padrão deve ser MySQL.' => config('database.default') === 'mysql',
        'A fila não pode usar o driver sync.' => config('queue.default') !== 'sync',
        'O mailer não pode usar log ou array.' => ! in_array(config('mail.default'), ['log', 'array'], true),
        'TODOIST_CLIENT_ID deve estar configurado.' => filled(config('services.todoist.client_id')),
        'TODOIST_CLIENT_SECRET deve estar configurado.' => filled(config('services.todoist.client_secret')),
        'TODOIST_WEBHOOK_SECRET deve estar configurado.' => filled(config('services.todoist.webhook_secret')),
    ];
    $failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));
    if ($failed === []) {
        $this->info('Prontidão de produção: aprovada.');

        return 0;
    }
    foreach ($failed as $message) {
        $this->error($message);
    }
    $this->error('Prontidão de produção: reprovada.');

    return 1;
})->purpose('Valida configurações obrigatórias antes do deploy em produção');
