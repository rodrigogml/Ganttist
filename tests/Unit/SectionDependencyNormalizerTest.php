<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scheduling\ScheduleDependency;
use App\Domain\Scheduling\SectionDependencyNormalizer;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SectionDependencyNormalizerTest extends TestCase
{
    public function test_section_start_constraint_is_inherited_without_overriding_a_later_task_date(): void
    {
        $result = $this->normalizer()->calculate([
            $this->task('source', '2026-09-07', '2026-09-08', 2),
            $this->task('early', '2026-09-07', '2026-09-07', 1),
            $this->task('late', '2026-09-14', '2026-09-14', 1),
        ], ['source' => null, 'early' => 'section', 'late' => 'section'], ['section' => null], [
            new ScheduleDependency('task', 'source', 'section', 'section', 'FS'),
        ], new DateTimeImmutable('2026-09-07'));

        self::assertSame('2026-09-09', $result['projections']['early']->consideredStart->format('Y-m-d'));
        self::assertSame('2026-09-14', $result['projections']['late']->consideredStart->format('Y-m-d'));
    }

    public function test_section_finish_constraint_moves_only_the_latest_descendant_anchor(): void
    {
        $result = $this->normalizer()->calculate([
            $this->task('source', '2026-09-07', '2026-09-10', 4),
            $this->task('early', '2026-09-07', '2026-09-07', 1),
            $this->task('anchor', '2026-09-07', '2026-09-08', 2),
        ], ['source' => null, 'early' => 'section', 'anchor' => 'section'], ['section' => null], [
            new ScheduleDependency('task', 'source', 'section', 'section', 'FF'),
        ], new DateTimeImmutable('2026-09-07'));

        self::assertSame('2026-09-07', $result['projections']['early']->consideredDeadline->format('Y-m-d'));
        self::assertSame('2026-09-10', $result['projections']['anchor']->consideredDeadline->format('Y-m-d'));
    }

    private function normalizer(): SectionDependencyNormalizer
    {
        return new SectionDependencyNormalizer(new WorkCalendar);
    }

    private function task(string $id, string $start, string $finish, int $durationWorkdays): TaskProjectionInput
    {
        return new TaskProjectionInput($id, new DateTimeImmutable($start), new DateTimeImmutable($finish), false, null, $durationWorkdays);
    }
}
