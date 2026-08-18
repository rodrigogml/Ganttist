<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class BenchmarkController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(config('services.benchmark.enabled'), 404);

        return view('benchmark');
    }
}
