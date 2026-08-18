<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

final class GroupScheduleCalculator
{
    /** @param array<string, TaskPlan> $tasks @return array<string, GroupSchedule> */
    public function calculate(array $tasks, WorkCalendar $calendar): array
    {
        $children = [];
        foreach ($tasks as $task) {
            if ($task->parentId !== null && isset($tasks[$task->parentId])) {
                $children[$task->parentId][] = $task->id;
            }
        }
        $result = [];
        $visit = function (string $id) use (&$visit, &$result, $tasks, $children, $calendar): ?GroupSchedule {
            $ranges = [];
            foreach ($children[$id] ?? [] as $childId) {
                $child = $tasks[$childId];
                $nested = $visit($childId);
                if ($nested !== null) {
                    $ranges[] = $nested;
                } elseif ($child->start !== null) {
                    $ranges[] = new GroupSchedule($child->start, $child->finish($calendar));
                }
            }
            if ($ranges === []) {
                return null;
            }
            $group = new GroupSchedule(
                min(array_map(fn (GroupSchedule $range) => $range->start, $ranges)),
                max(array_map(fn (GroupSchedule $range) => $range->finish, $ranges)),
            );
            $result[$id] = $group;

            return $group;
        };
        foreach (array_keys($children) as $id) {
            $visit($id);
        }

        return $result;
    }
}
