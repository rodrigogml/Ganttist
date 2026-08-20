<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TodoistTask;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TodoistTaskTest extends TestCase
{
    #[Test]
    public function it_normalizes_current_and_legacy_task_date_and_completion_shapes(): void
    {
        $current = ['due' => ['date' => '2026-08-19'], 'deadline' => ['date' => '2026-08-21'], 'checked' => true];
        $legacy = ['due' => ['date' => '2026-08-19'], 'deadline_date' => '2026-08-20', 'is_completed' => false];

        self::assertSame('2026-08-19', TodoistTask::start($current));
        self::assertSame('2026-08-21', TodoistTask::finish($current));
        self::assertTrue(TodoistTask::completed($current));
        self::assertSame('2026-08-20', TodoistTask::finish($legacy));
        self::assertFalse(TodoistTask::completed($legacy));
    }
}
