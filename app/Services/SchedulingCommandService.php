<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\TaskPlan;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SchedulingCommandService
{
    public function __construct(private AuthorizedPlanningSnapshot $snapshots) {}

    /** @param array{taskId: string, start: string, finish?: string|null} $intent */
    public function simulate(object $project, object $integration, array $intent): array
    {
        $snapshot = $this->snapshots->load($project, $integration);
        $beforeTasks = $snapshot['tasks'];
        $task = $snapshot['tasks'][$intent['taskId']] ?? throw new InvalidArgumentException('A tarefa a reagendar não pertence ao projeto Todoist selecionado.');
        if ($task->completed) {
            throw new InvalidArgumentException('Uma tarefa concluída não pode ser reagendada.');
        }
        $start = new DateTimeImmutable($intent['start']);
        $duration = $task->duration;
        if (! empty($intent['finish'])) {
            $finish = new DateTimeImmutable($intent['finish']);
            if ($finish < $start) {
                throw new InvalidArgumentException('A data final não pode ser anterior à inicial.');
            }
            $duration = $snapshot['calendar']->countWorkDays($start, $finish);
        }
        $snapshot['tasks'][$task->id] = new TaskPlan($task->id, $task->title, $start, $duration, false, null, $task->parentId);
        $result = (new SchedulingEngine($snapshot['calendar']))->schedule(array_values($snapshot['tasks']), $snapshot['dependencies'], now()->startOfDay()->toDateTimeImmutable());

        return ['result' => $result, 'calendar' => $snapshot['calendar'], 'before_tasks' => $beforeTasks];
    }
}
