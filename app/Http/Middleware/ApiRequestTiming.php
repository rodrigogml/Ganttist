<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApiRequestTiming
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->log($request, $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500, $startedAt, $exception::class);

            throw $exception;
        }

        if ($request->is('api/*')) {
            $this->log($request, $response->getStatusCode(), $startedAt);
        }

        return $response;
    }

    private function log(Request $request, int $status, int $startedAt, ?string $exception = null): void
    {
        if (! $request->is('api/*')) {
            return;
        }
        Log::debug('http.request.completed', array_filter([
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'exception' => $exception,
        ]));
    }
}
