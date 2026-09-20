<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

Artisan::command('documents:readiness {--write : Executa escrita, leitura e remoção no disco documental}', function (): int {
    $disk = (string) config('documents.disk');
    $processor = new Process([(string) config('documents.processor.python'), '-c', 'import pypdf; print(pypdf.__version__)']);
    $processor->setTimeout(15);
    try {
        $processor->run();
        $processorReady = $processor->isSuccessful() && trim($processor->getOutput()) === '6.7.1';
    } catch (Throwable) {
        $processorReady = false;
    }

    $tables = [
        'project_documents', 'project_document_revisions', 'project_tags', 'project_document_tag',
        'project_document_derivations', 'project_document_derivation_runs',
    ];
    $checks = [
        'As migrations documentais precisam estar aplicadas.' => collect($tables)->every(fn (string $table): bool => Schema::hasTable($table)),
        'O disco documental precisa existir no Filesystem.' => filled(config("filesystems.disks.{$disk}.driver")),
        'O limite documental precisa permitir 500 MiB.' => (int) config('documents.max_upload_kb') >= 512000,
        'retry_after da fila documental precisa superar o timeout do job.' => (int) config('queue.connections.documents.retry_after') > 300,
        'O timeout do processador precisa ser menor que o timeout do job.' => (int) config('documents.processor.timeout_seconds') < 300,
        'O script do processador PDF precisa existir.' => is_file((string) config('documents.processor.script')),
        'Python precisa carregar exatamente pypdf 6.7.1.' => $processorReady,
        'O disco S3 precisa impor visibilidade privada.' => $disk !== 's3' || config('filesystems.disks.s3.visibility') === 'private',
        'O bucket S3 precisa estar configurado.' => $disk !== 's3' || filled(config('filesystems.disks.s3.bucket')),
    ];

    if ($this->option('write') && filled(config("filesystems.disks.{$disk}.driver"))) {
        $key = 'health/documents/'.Str::uuid().'/probe.txt';
        try {
            $filesystem = Storage::disk($disk);
            $written = $filesystem->put($key, 'ganttist-document-storage-probe', ['visibility' => 'private']);
            $checks['O disco documental precisa gravar e ler conteúdo privado.'] = $written
                && $filesystem->exists($key)
                && $filesystem->get($key) === 'ganttist-document-storage-probe';
        } catch (Throwable) {
            $checks['O disco documental precisa gravar e ler conteúdo privado.'] = false;
        } finally {
            try {
                Storage::disk($disk)->delete($key);
            } catch (Throwable) {
                // A falha já será indicada pelo check de leitura/escrita.
            }
        }
    }

    $failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));
    if ($failed === []) {
        $this->info('Prontidão documental: aprovada.');

        return 0;
    }
    foreach ($failed as $message) {
        $this->error($message);
    }
    $this->error('Prontidão documental: reprovada.');

    return 1;
})->purpose('Valida migrations, storage, fila e processador da biblioteca documental');
