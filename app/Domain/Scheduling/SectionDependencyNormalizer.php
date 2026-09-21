<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

/** Normaliza relações de seção para relações entre tarefas-folha. */
final readonly class SectionDependencyNormalizer
{
    public function __construct(private WorkCalendar $calendar, private ProjectionPolicy $policy = ProjectionPolicy::PreserveDuration) {}

    /**
     * @param  list<TaskProjectionInput>  $tasks
     * @param  array<string, string|null>  $taskSections
     * @param  array<string, string|null>  $sectionParents
     * @param  list<ScheduleDependency>  $dependencies
     * @return array{projections: array<string, TaskProjection>, dependencies: list<Dependency>, violations: array<string, bool>}
     */
    public function calculate(array $tasks, array $taskSections, array $sectionParents, array $dependencies, DateTimeImmutable $today): array
    {
        $inputs = [];
        foreach ($tasks as $task) {
            $inputs[$task->id] = $task;
        }
        $descendants = $this->descendants($inputs, $taskSections, $sectionParents);
        $projections = (new TaskProjectionCalculator($this->calendar, $this->policy))->calculate($tasks, [], $today);
        $normalized = [];
        $violations = [];

        for ($pass = 0; $pass < max(1, count($tasks) + count($dependencies) + 1); $pass++) {
            $next = [];
            $violations = [];
            foreach ($dependencies as $index => $dependency) {
                $sources = $dependency->predecessorKind === 'task'
                    ? [$dependency->predecessorId]
                    : $this->anchor($descendants[$dependency->predecessorId] ?? [], $projections, in_array($dependency->type, ['FS', 'FF'], true));
                $targets = $dependency->successorKind === 'task'
                    ? [$dependency->successorId]
                    : $this->targets($descendants[$dependency->successorId] ?? [], $projections, $dependency->type);
                if ($sources === [] || $targets === []) {
                    $violations[(string) $index] = true;

                    continue;
                }
                foreach ($sources as $source) {
                    foreach ($targets as $target) {
                        $next[] = new Dependency($source, $target, $dependency->type);
                    }
                }
            }
            $unique = [];
            foreach ($next as $dependency) {
                $unique[implode('|', [$dependency->predecessorId, $dependency->successorId, $dependency->type])] = $dependency;
            }
            $normalized = array_values($unique);
            $updated = (new TaskProjectionCalculator($this->calendar, $this->policy))->calculate($tasks, $normalized, $today);
            if ($this->same($projections, $updated)) {
                return ['projections' => $updated, 'dependencies' => $normalized, 'violations' => $violations];
            }
            $projections = $updated;
        }
        throw new InvalidArgumentException('O cálculo de dependências de seção não estabilizou.');
    }

    /** @param array<string, TaskProjectionInput> $inputs @param array<string, string|null> $taskSections @param array<string, string|null> $sectionParents @return array<string, list<string>> */
    private function descendants(array $inputs, array $taskSections, array $sectionParents): array
    {
        $result = [];
        foreach ($inputs as $id => $_) {
            $section = $taskSections[$id] ?? null;
            while ($section !== null) {
                $result[$section][] = $id;
                $section = $sectionParents[$section] ?? null;
            }
        }

        return $result;
    }

    /** @param list<string> $ids @param array<string, TaskProjection> $projections @return list<string> */
    private function anchor(array $ids, array $projections, bool $finish): array
    {
        $available = array_values(array_filter($ids, fn (string $id): bool => isset($projections[$id])));
        if ($available === []) {
            return [];
        }
        usort($available, function (string $left, string $right) use ($projections, $finish): int {
            $a = $finish ? $projections[$left]->consideredDeadline : $projections[$left]->consideredStart;
            $b = $finish ? $projections[$right]->consideredDeadline : $projections[$right]->consideredStart;
            $comparison = $a <=> $b;

            return $comparison === 0 ? $left <=> $right : ($finish ? -$comparison : $comparison);
        });

        return [$available[0]];
    }

    /** @param list<string> $ids @param array<string, TaskProjection> $projections @return list<string> */
    private function targets(array $ids, array $projections, string $type): array
    {
        if (in_array($type, ['FS', 'SS'], true)) {
            return $ids;
        }

        return $this->anchor($ids, $projections, true);
    }

    /** @param array<string, TaskProjection> $before @param array<string, TaskProjection> $after */
    private function same(array $before, array $after): bool
    {
        foreach ($before as $id => $projection) {
            if (! isset($after[$id]) || $projection->consideredStart != $after[$id]->consideredStart || $projection->consideredDeadline != $after[$id]->consideredDeadline) {
                return false;
            }
        }

        return true;
    }
}
