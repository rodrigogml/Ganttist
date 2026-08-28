<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TaskProjectionCalculator
{
    public function __construct(
        private WorkCalendar $calendar,
        private ProjectionPolicy $policy = ProjectionPolicy::PreserveDuration,
    ) {}

    /**
     * @param  list<TaskProjectionInput>  $tasks
     * @param  list<Dependency>  $dependencies
     * @return array<string, TaskProjection>
     */
    public function calculate(array $tasks, array $dependencies, DateTimeImmutable $today): array
    {
        $today = $this->date($today);
        $inputs = [];
        foreach ($tasks as $task) {
            if (isset($inputs[$task->id])) {
                throw new InvalidArgumentException("Tarefa duplicada na projeção: {$task->id}.");
            }
            $inputs[$task->id] = $task;
        }

        $incoming = [];
        $outgoing = [];
        $degree = array_fill_keys(array_keys($inputs), 0);
        foreach ($dependencies as $dependency) {
            if (! isset($inputs[$dependency->predecessorId], $inputs[$dependency->successorId])) {
                throw new InvalidArgumentException('Dependência referencia tarefa ausente da projeção.');
            }
            $incoming[$dependency->successorId][] = $dependency;
            $outgoing[$dependency->predecessorId][] = $dependency->successorId;
            $degree[$dependency->successorId]++;
        }

        $queue = array_keys(array_filter($degree, fn (int $value): bool => $value === 0));
        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($outgoing[$id] ?? [] as $successorId) {
                $degree[$successorId]--;
                if ($degree[$successorId] === 0) {
                    $queue[] = $successorId;
                }
            }
        }
        if (count($order) !== count($inputs)) {
            throw new InvalidArgumentException('O grafo de projeção contém ciclo.');
        }

        $result = [];
        foreach ($order as $id) {
            $input = $inputs[$id];
            $baseStart = $this->date($input->start ?? $today);
            $explicitDeadline = $input->deadline ? $this->date($input->deadline) : null;
            $baseDeadline = $explicitDeadline !== null && $explicitDeadline >= $baseStart ? $explicitDeadline : $baseStart;
            $duration = $this->calendar->countWorkDays($baseStart, $baseDeadline);
            $consideredStart = $baseStart;
            $unlockDate = null;
            $earliestStart = null;
            $blocked = false;

            foreach ($incoming[$id] ?? [] as $dependency) {
                $predecessorInput = $inputs[$dependency->predecessorId];
                $predecessor = $result[$dependency->predecessorId];
                $candidate = match ($dependency->type) {
                    'FS' => $predecessorInput->completed
                        ? $predecessor->effectiveCompletionDate
                        : $this->calendar->nextWorkDay($predecessor->consideredDeadline),
                    'SS' => $predecessor->consideredStart,
                    'FF' => $this->calendar->subtractWorkDays($predecessor->consideredDeadline, $duration - 1),
                    'SF' => $this->calendar->subtractWorkDays($predecessor->consideredStart, $duration - 1),
                };
                if ($dependency->type === 'FS') {
                    $unlockDate = $unlockDate === null || $candidate > $unlockDate ? $candidate : $unlockDate;
                    $blocked = $blocked || ! $predecessorInput->completed;
                }
                $earliestStart = $earliestStart === null || $candidate > $earliestStart ? $candidate : $earliestStart;
                if ($candidate > $consideredStart) {
                    $consideredStart = $candidate;
                }
            }

            $consideredDeadline = match ($this->policy) {
                ProjectionPolicy::PreserveDuration => $consideredStart == $baseStart
                    ? $baseDeadline
                    : ($duration === 1 ? $consideredStart : $this->calendar->addWorkDays($consideredStart, $duration - 1)),
                ProjectionPolicy::PreserveDeadline => $baseDeadline >= $consideredStart ? $baseDeadline : $consideredStart,
            };
            $completionDate = $this->date($input->completionDate ?? $today);
            $hasExplicitSchedule = $input->start !== null || $input->deadline !== null;
            $status = match (true) {
                $input->completed => ProjectedTaskStatus::Completed,
                $blocked => ProjectedTaskStatus::Blocked,
                $consideredStart > $today => ProjectedTaskStatus::Scheduled,
                $consideredDeadline < $today => ProjectedTaskStatus::Late,
                $hasExplicitSchedule => ProjectedTaskStatus::InProgress,
                default => ProjectedTaskStatus::Opened,
            };
            $result[$id] = new TaskProjection($id, $consideredStart, $consideredDeadline, $unlockDate, $earliestStart, $completionDate, $status);
        }

        return $result;
    }

    private function date(DateTimeImmutable $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value->format('Y-m-d'));
    }
}
