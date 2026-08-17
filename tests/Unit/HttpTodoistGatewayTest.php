<?php

namespace Tests\Unit;

use App\Infrastructure\Todoist\HttpTodoistGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HttpTodoistGatewayTest extends TestCase
{
    public function test_project_snapshot_uses_the_expected_todoist_endpoints(): void
    {
        Http::fake([
            '*/projects' => Http::response(['results' => [['id' => 'p1']]]),
            '*/sections*' => Http::response(['results' => [['id' => 's1']]]),
            '*/tasks*' => Http::response(['results' => [['id' => 't1']]]),
        ]);

        $snapshot = (new HttpTodoistGateway)->projectSnapshot('token', 'p1');

        self::assertSame('t1', $snapshot['tasks']['results'][0]['id']);
        Http::assertSentCount(2);
    }
}
