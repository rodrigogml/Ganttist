<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class TodoistUserIdentityService
{
    public function resolve(string $accessToken): ?string
    {
        $response = Http::baseUrl((string) config('services.todoist.api_url'))
            ->withToken($accessToken)
            ->asForm()
            ->acceptJson()
            ->timeout(15)
            ->post('/sync', [
                'sync_token' => '*',
                'resource_types' => json_encode(['user'], JSON_THROW_ON_ERROR),
            ])
            ->throw()
            ->json();
        $userId = $response['user']['id'] ?? null;

        return is_int($userId) || is_string($userId) ? (string) $userId : null;
    }
}
