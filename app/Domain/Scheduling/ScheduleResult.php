<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

final readonly class ScheduleResult
{
    /** @param array<string, TaskPlan> $tasks @param list<string> $changedTaskIds @param array<string, int> $totalFloat */
    public function __construct(
        public array $tasks,
        public array $changedTaskIds,
        public array $totalFloat,
        public array $criticalTaskIds,
        public array $topologicalOrder,
    ) {}
}
