<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\ProjectedTaskStatus;
use App\Domain\Scheduling\ProjectionPolicy;
use App\Domain\Scheduling\TaskProjectionCalculator;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TaskProjectionCalculatorTest extends TestCase
{
    public function test_it_calculates_the_canonical_status_precedence(): void
    {
        $result = $this->calculator()->calculate([
            $this->task('completed', null, null, true),
            $this->task('opened'),
            $this->task('scheduled', '2026-08-24', '2026-08-24'),
            $this->task('late', '2026-08-17', '2026-08-19'),
            $this->task('blocker', '2026-08-20', '2026-08-21'),
            $this->task('blocked', '2026-08-20', '2026-08-20'),
        ], [new Dependency('blocker', 'blocked', 'FS')], new DateTimeImmutable('2026-08-20'));

        self::assertSame(ProjectedTaskStatus::Completed, $result['completed']->status);
        self::assertSame(ProjectedTaskStatus::Opened, $result['opened']->status);
        self::assertSame(ProjectedTaskStatus::Scheduled, $result['scheduled']->status);
        self::assertSame(ProjectedTaskStatus::Late, $result['late']->status);
        self::assertSame(ProjectedTaskStatus::Blocked, $result['blocked']->status);
        self::assertSame('2026-08-24', $result['blocked']->unlockDate?->format('Y-m-d'));
    }

    public function test_only_fs_blocks_but_every_dependency_type_constrains_dates(): void
    {
        $result = $this->calculator()->calculate([
            $this->task('predecessor', '2026-08-24', '2026-08-26'),
            $this->task('successor', '2026-08-20', '2026-08-20'),
        ], [new Dependency('predecessor', 'successor', 'SS')], new DateTimeImmutable('2026-08-20'));

        self::assertSame(ProjectedTaskStatus::Scheduled, $result['successor']->status);
        self::assertSame('2026-08-24', $result['successor']->consideredStart->format('Y-m-d'));
        self::assertNull($result['successor']->unlockDate);
        self::assertSame('2026-08-24', $result['successor']->earliestStart?->format('Y-m-d'));
    }

    public function test_completed_fs_predecessor_unlocks_on_its_effective_completion_date(): void
    {
        $result = $this->calculator()->calculate([
            $this->task('predecessor', '2026-08-17', '2026-08-19', true, '2026-08-20'),
            $this->task('successor'),
        ], [new Dependency('predecessor', 'successor', 'FS')], new DateTimeImmutable('2026-08-20'));

        self::assertSame(ProjectedTaskStatus::Opened, $result['successor']->status);
        self::assertSame('2026-08-20', $result['successor']->unlockDate?->format('Y-m-d'));
        self::assertSame('2026-08-20', $result['successor']->consideredStart->format('Y-m-d'));
    }

    public function test_projection_policy_preserves_duration_or_clamps_the_deadline_without_mutating_source_dates(): void
    {
        $tasks = [
            $this->task('predecessor', '2026-08-20', '2026-08-24'),
            $this->task('successor', '2026-08-20', '2026-08-22'),
        ];
        $dependencies = [new Dependency('predecessor', 'successor', 'FS')];
        $today = new DateTimeImmutable('2026-08-20');

        $duration = $this->calculator()->calculate($tasks, $dependencies, $today)['successor'];
        $deadline = $this->calculator(ProjectionPolicy::PreserveDeadline)->calculate($tasks, $dependencies, $today)['successor'];

        self::assertSame('2026-08-25', $duration->consideredStart->format('Y-m-d'));
        self::assertSame('2026-08-26', $duration->consideredDeadline->format('Y-m-d'));
        self::assertSame('2026-08-25', $deadline->consideredStart->format('Y-m-d'));
        self::assertSame('2026-08-25', $deadline->consideredDeadline->format('Y-m-d'));
    }

    private function calculator(ProjectionPolicy $policy = ProjectionPolicy::PreserveDuration): TaskProjectionCalculator
    {
        return new TaskProjectionCalculator(new WorkCalendar, $policy);
    }

    private function task(string $id, ?string $start = null, ?string $deadline = null, bool $completed = false, ?string $completionDate = null): TaskProjectionInput
    {
        return new TaskProjectionInput(
            $id,
            $start ? new DateTimeImmutable($start) : null,
            $deadline ? new DateTimeImmutable($deadline) : null,
            $completed,
            $completionDate ? new DateTimeImmutable($completionDate) : null,
        );
    }
}
