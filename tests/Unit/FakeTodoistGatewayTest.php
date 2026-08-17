<?php

namespace Tests\Unit;

use App\Infrastructure\Todoist\FakeTodoistGateway;
use PHPUnit\Framework\TestCase;

final class FakeTodoistGatewayTest extends TestCase
{
    public function test_fake_gateway_is_deterministic_and_supports_writes(): void
    {
        $gateway = new FakeTodoistGateway;
        self::assertSame('fake-project', $gateway->projects('token')['results'][0]['id']);
        self::assertSame('fake-task-1', $gateway->projectSnapshot('token', 'fake-project')['tasks']['results'][0]['id']);
        self::assertSame('2026-08-19', $gateway->updateTaskDates('token', 'fake-task-1', '2026-08-17', '2026-08-19')['deadline_date']);
    }
}
