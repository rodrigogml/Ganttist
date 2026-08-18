<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class SchedulingEngine
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /** @param list<TaskPlan> $tasks @param list<Dependency> $dependencies */
    public function schedule(array $tasks, array $dependencies, DateTimeImmutable $localToday): ScheduleResult
    {
        $byId = [];
        foreach ($tasks as $task) {
            $byId[$task->id] = $task;
        }
        $groupIds = [];
        foreach ($byId as $task) {
            if ($task->parentId !== null && isset($byId[$task->parentId])) {
                $groupIds[$task->parentId] = true;
            }
        }
        if ($groupIds !== []) {
            $leafTasks = array_values(array_filter($byId, fn (TaskPlan $task): bool => ! isset($groupIds[$task->id])));
            $leafDependencies = [];
            $groupDependencies = [];
            foreach ($dependencies as $dependency) {
                if (isset($groupIds[$dependency->successorId])) {
                    throw new InvalidArgumentException('Um grupo não pode ser sucessor de uma dependência.');
                }
                if (isset($groupIds[$dependency->predecessorId])) {
                    $groupDependencies[] = $dependency;
                } else {
                    $leafDependencies[] = $dependency;
                }
            }
            if ($groupDependencies === []) {
                return $this->schedule($leafTasks, $leafDependencies, $localToday);
            }
            $initial = $this->schedule($leafTasks, $leafDependencies, $localToday);
            $normalized = (new GroupDependencyResolver($this->calendar))->normalize($byId, $initial, $groupDependencies);
            $unique = [];
            foreach ([...$leafDependencies, ...$normalized] as $dependency) {
                $unique[implode('|', [$dependency->predecessorId, $dependency->successorId, $dependency->type])] = $dependency;
            }

            return $this->schedule($leafTasks, array_values($unique), $localToday);
        }
        $originalTasks = $byId;
        $this->validateDependencies($byId, $dependencies);
        $order = $this->topologicalOrder(array_keys($byId), $dependencies);
        $incoming = $this->groupIncoming($dependencies);
        $operationalToday = $this->calendar->operationalToday($localToday);
        $changed = [];

        foreach ($order as $id) {
            $task = $byId[$id];
            $virtualStart = $task->start ?? $operationalToday;
            if ($task->completed) {
                continue;
            }
            $requiredStart = $this->calendar->onOrAfter($virtualStart);
            foreach ($incoming[$id] ?? [] as $dependency) {
                $predecessor = $byId[$dependency->predecessorId];
                $predecessorStart = $predecessor->effectiveCompletionDate
                    ?? $predecessor->start
                    ?? $operationalToday;
                $predecessorFinish = $predecessor->completed && $predecessor->effectiveCompletionDate
                    ? $predecessor->effectiveCompletionDate
                    : $predecessor->finish($this->calendar, $operationalToday);
                $candidate = match ($dependency->type) {
                    'FS' => $this->calendar->nextWorkDay($predecessorFinish),
                    'SS' => $this->calendar->onOrAfter($predecessorStart),
                    'FF' => $this->calendar->subtractWorkDays($predecessorFinish, $task->duration - 1),
                    'SF' => $this->calendar->subtractWorkDays($predecessorStart, $task->duration - 1),
                };
                if ($candidate > $requiredStart) {
                    $requiredStart = $candidate;
                }
            }

            // A data virtual participa dos cálculos sem transformar a tarefa em programada.
            if ($task->start !== null && $requiredStart > $task->start) {
                $byId[$id] = $task->withStart($requiredStart);
                $changed[] = $id;
            } elseif ($task->start === null) {
                $byId[$id] = $task->withStart($requiredStart);
            }
        }

        [$float, $critical] = $this->calculateFloat($byId, $dependencies, $order);

        $virtualStarts = [];
        $publicTasks = $byId;
        foreach ($originalTasks as $id => $original) {
            if ($original->start === null) {
                $virtualStarts[$id] = $byId[$id]->start ?? $operationalToday;
                $publicTasks[$id] = $original;
            }
        }

        return new ScheduleResult($publicTasks, $changed, $float, $critical, $order, $virtualStarts);
    }

    /** @param array<string, TaskPlan> $tasks @param list<Dependency> $dependencies */
    private function validateDependencies(array $tasks, array $dependencies): void
    {
        $seen = [];
        foreach ($dependencies as $dependency) {
            if (! isset($tasks[$dependency->predecessorId], $tasks[$dependency->successorId])) {
                throw new InvalidArgumentException('Dependência referencia tarefa fora do projeto.');
            }
            $key = implode('|', [$dependency->predecessorId, $dependency->successorId, $dependency->type]);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Dependência duplicada.');
            }
            $seen[$key] = true;
        }
    }

    /** @param list<string> $ids @param list<Dependency> $dependencies @return list<string> */
    private function topologicalOrder(array $ids, array $dependencies): array
    {
        $degree = array_fill_keys($ids, 0);
        $outgoing = [];
        foreach ($dependencies as $dependency) {
            $degree[$dependency->successorId]++;
            $outgoing[$dependency->predecessorId][] = $dependency->successorId;
        }
        $queue = array_values(array_filter($ids, fn (string $id) => $degree[$id] === 0));
        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($outgoing[$id] ?? [] as $successor) {
                if (--$degree[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }
        if (count($order) !== count($ids)) {
            $cycleIds = array_keys(array_filter($degree, fn (int $value) => $value > 0));
            throw new DomainException('A dependência criaria um ciclo: '.implode(' → ', $cycleIds));
        }

        return $order;
    }

    /** @param list<Dependency> $dependencies */
    private function groupIncoming(array $dependencies): array
    {
        $incoming = [];
        foreach ($dependencies as $dependency) {
            $incoming[$dependency->successorId][] = $dependency;
        }

        return $incoming;
    }

    /** @param array<string, TaskPlan> $tasks @param list<Dependency> $dependencies @param list<string> $order */
    private function calculateFloat(array $tasks, array $dependencies, array $order): array
    {
        if ($tasks === []) {
            return [[], []];
        }
        $projectFinish = max(array_map(fn (TaskPlan $task) => $task->finish($this->calendar), $tasks));
        $outgoing = [];
        foreach ($dependencies as $dependency) {
            $outgoing[$dependency->predecessorId][] = $dependency;
        }
        $lateStart = [];
        $lateFinish = [];
        foreach (array_reverse($order) as $id) {
            $task = $tasks[$id];
            $lf = $projectFinish;
            $ls = $this->calendar->subtractWorkDays($lf, $task->duration - 1);
            foreach ($outgoing[$id] ?? [] as $dependency) {
                $successor = $tasks[$dependency->successorId];
                $successorLs = $lateStart[$successor->id];
                $successorLf = $lateFinish[$successor->id];
                [$candidateLs, $candidateLf] = match ($dependency->type) {
                    'FS' => [null, $this->calendar->previousWorkDay($successorLs)],
                    'SS' => [$successorLs, null],
                    'FF' => [null, $successorLf],
                    'SF' => [$successorLf, null],
                };
                if ($candidateLf !== null && $candidateLf < $lf) {
                    $lf = $candidateLf;
                    $ls = $this->calendar->subtractWorkDays($lf, $task->duration - 1);
                }
                if ($candidateLs !== null && $candidateLs < $ls) {
                    $ls = $candidateLs;
                    $lf = $this->calendar->addWorkDays($ls, $task->duration - 1);
                }
            }
            $lateStart[$id] = $ls;
            $lateFinish[$id] = $lf;
        }

        $float = [];
        foreach ($tasks as $id => $task) {
            $early = $task->start ?? $task->effectiveCompletionDate ?? throw new DomainException('Data virtual ausente.');
            $float[$id] = $lateStart[$id] <= $early
                ? 0
                : $this->calendar->countWorkDays($early, $lateStart[$id]) - 1;
        }
        $critical = array_keys(array_filter($float, fn (int $days) => $days === 0));

        return [$float, $critical];
    }
}
