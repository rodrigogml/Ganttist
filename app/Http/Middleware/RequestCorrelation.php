<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestCorrelation
{
    public function handle(Request $request, Closure $next): Response
    {
        $supplied = trim((string) $request->header('X-Request-ID'));
        $requestId = preg_match('/^[A-Za-z0-9_-]{8,64}$/', $supplied) === 1 ? $supplied : (string) Str::ulid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
