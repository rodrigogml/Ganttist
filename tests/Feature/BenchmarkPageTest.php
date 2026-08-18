<?php

namespace Tests\Feature;

use Tests\TestCase;

final class BenchmarkPageTest extends TestCase
{
    public function test_benchmark_page_is_feature_gated_and_uses_a_bounded_virtual_window(): void
    {
        $this->get('/benchmark')->assertNotFound();
        config()->set('services.benchmark.enabled', true);
        $this->get('/benchmark')->assertOk()->assertSee('windowFor')->assertSee('NÓS NO DOM')->assertSee('5000');
    }

    public function test_web_routes_are_cacheable_for_production(): void
    {
        $this->artisan('route:cache')->assertSuccessful();
        $this->artisan('route:clear')->assertSuccessful();
    }
}
