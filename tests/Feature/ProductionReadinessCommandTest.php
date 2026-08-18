<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ProductionReadinessCommandTest extends TestCase
{
    public function test_readiness_rejects_the_default_local_configuration(): void
    {
        $this->artisan('app:production-readiness')
            ->expectsOutput('APP_ENV deve ser production.')
            ->expectsOutput('Prontidão de produção: reprovada.')
            ->assertFailed();
    }

    public function test_readiness_accepts_a_complete_production_configuration(): void
    {
        config()->set([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://ganttist.example.test', 'app.key' => 'base64:test-key',
            'session.secure' => true, 'database.default' => 'mysql', 'queue.default' => 'database', 'mail.default' => 'smtp',
            'services.todoist.client_id' => 'client', 'services.todoist.client_secret' => 'secret', 'services.todoist.webhook_secret' => 'webhook',
        ]);

        $this->artisan('app:production-readiness')->expectsOutput('Prontidão de produção: aprovada.')->assertSuccessful();
    }
}
