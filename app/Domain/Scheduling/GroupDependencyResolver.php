<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use InvalidArgumentException;

final readonly class GroupDependencyResolver
{
    public function __construct(private WorkCalendar $calendar) {}

    /**
     * Reescreve uma precedência cujo predecessor é grupo para a folha que
     * atualmente determina a data derivada relevante desse grupo.
     *
     * @param  array<string, TaskPlan>  $allTasks
     * @param  list<Dependency>  $groupDependencies
     * @return list<Dependency>
     */
    public function normalize(array $allTasks, ScheduleResult $scheduledLeaves, array $groupDependencies): array
    {
        $children = [];
        foreach ($allTasks as $task) {
            if ($task->parentId !== null && isset($allTasks[$task->parentId])) {
                $children[$task->parentId][] = $task->id;
            }
        }
        $descendants = function (string $groupId) use (&$descendants, $children): array {
            $leaves = [];
            foreach ($children[$groupId] ?? [] as $childId) {
                if (isset($children[$childId])) {
                    $leaves = [...$leaves, ...$descendants($childId)];
                } else {
                    $leaves[] = $childId;
                }
            }

            return $leaves;
        };

        $normalized = [];
        foreach ($groupDependencies as $dependency) {
            $candidates = [];
            foreach ($descendants($dependency->predecessorId) as $id) {
                $task = $scheduledLeaves->tasks[$id] ?? null;
                if ($task === null) {
                    continue;
                }
                $start = $task->start ?? ($scheduledLeaves->virtualStarts[$id] ?? null);
                if ($start !== null) {
                    $candidates[] = $task->withStart($start);
                }
            }
            if ($candidates === []) {
                // Um grupo sem descendente planejável não impõe restrição temporal.
                continue;
            }

            $controller = array_reduce(
                $candidates,
                function (?TaskPlan $selected, TaskPlan $candidate) use ($dependency): TaskPlan {
                    if ($selected === null) {
                        return $candidate;
                    }

                    return match ($dependency->type) {
                        'FS', 'FF' => $candidate->finish($this->calendar) > $selected->finish($this->calendar) ? $candidate : $selected,
                        'SS', 'SF' => $candidate->start < $selected->start ? $candidate : $selected,
                    };
                },
            );
            if ($controller === null) {
                throw new InvalidArgumentException('Não foi possível derivar a data do grupo predecessor.');
            }
            $normalized[] = new Dependency($controller->id, $dependency->successorId, $dependency->type);
        }

        return $normalized;
    }
}
