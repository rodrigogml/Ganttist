<?php

use App\Exceptions\TodoistReauthorizationRequired;
use App\Http\Middleware\ApiRequestTiming;
use App\Http\Middleware\RequestCorrelation;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
        $middleware->append(RequestCorrelation::class);
        $middleware->append(ApiRequestTiming::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('todoist:sync')->hourly()->withoutOverlapping();
        $schedule->command('audit:prune')->daily()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (TodoistReauthorizationRequired $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return redirect('/?todoist=reauthorization_required');
        });
    })->create();
