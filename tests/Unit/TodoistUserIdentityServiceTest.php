<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TodoistUserIdentityService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TodoistUserIdentityServiceTest extends TestCase
{
    public function test_it_resolves_the_authenticated_user_through_the_sync_resource(): void
    {
        Http::fake(['*/sync' => Http::response(['user' => ['id' => 431774]])]);

        self::assertSame('431774', app(TodoistUserIdentityService::class)->resolve('access-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.todoist.com/api/v1/sync'
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request['sync_token'] === '*'
            && $request['resource_types'] === '["user"]');
    }
}
