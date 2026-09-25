<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\ScheduleDependency;
use App\Domain\Scheduling\ScheduleResult;
use App\Domain\Scheduling\SchedulingEngine;
use App\Domain\Scheduling\SectionDependencyNormalizer;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\TaskPlanningState;
use App\Domain\Scheduling\TaskProjection;
use App\Domain\Scheduling\TaskProjectionInput;
use App\Domain\Scheduling\WorkCalendar;
use DateTimeImmutable;

final readonly class ProjectWorkspaceProjectionService
{
    /**
     * @param  iterable<object>  $tasks
     * @param  iterable<object>  $sections
     * @param  iterable<object>  $dependencyRows
     * @param  array<string, TaskPlanningState>  $planningOverrides
     * @return array{calculation: ScheduleResult, projections: array<string, TaskProjection>, dependencies: list<Dependency>, sectionParents: array<string, string|null>, projectFinish: string|null}
     */
    public function calculate(iterable $tasks, iterable $sections, iterable $dependencyRows, DateTimeImmutable $today, WorkCalendar $calendar, array $planningOverrides = []): array
    {
        $tasks = is_array($tasks) ? $tasks : iterator_to_array($tasks);
        $sectionParents = [];
        foreach ($sections as $section) {
            $sectionParents[$section->id] = $section->parent_section_id;
        }
        $taskSections = [];
        foreach ($tasks as $task) {
            $taskSections[$task->id] = $task->section_id;
        }
        $dependencies = [];
        foreach ($dependencyRows as $edge) {
            $dependencies[] = new ScheduleDependency($edge->predecessor_kind, $edge->predecessor_id, $edge->successor_kind, $edge->successor_id, $edge->type);
        }
        $inputs = [];
        foreach ($tasks as $task) {
            $state = $planningOverrides[$task->id] ?? new TaskPlanningState(
                $task->planned_start ? new DateTimeImmutable($task->planned_start) : null,
                $task->planned_finish ? new DateTimeImmutable($task->planned_finish) : null,
                $task->plannedDurationWorkdays === null ? null : (int) $task->plannedDurationWorkdays,
            );
            $inputs[] = new TaskProjectionInput($task->id, $state->start, $state->finish, $task->completed_at !== null, $task->completed_at ? new DateTimeImmutable($task->completed_at) : null, $state->durationWorkdays);
        }
        $sectionCalculation = (new SectionDependencyNormalizer($calendar))->calculate($inputs, $taskSections, $sectionParents, $dependencies, $today);
        $calculation = (new SchedulingEngine($calendar))->schedule(array_map(function (object $task) use ($planningOverrides, $calendar): TaskPlan {
            $state = $planningOverrides[$task->id] ?? new TaskPlanningState(
                $task->planned_start ? new DateTimeImmutable($task->planned_start) : null,
                $task->planned_finish ? new DateTimeImmutable($task->planned_finish) : null,
                $task->plannedDurationWorkdays === null ? null : (int) $task->plannedDurationWorkdays,
            );

            return TaskPlan::fromDates($task->id, $task->title, $state->start, $state->finish, $calendar, $task->completed_at !== null, $task->completed_at ? new DateTimeImmutable($task->completed_at) : null, null, $state->durationWorkdays, $task->recurrenceRule === null);
        }, $tasks), $sectionCalculation['dependencies'], $today);

        $finishes = [];
        foreach ($calculation->tasks as $id => $task) {
            if (! $task->participatesInFiniteNetwork) {
                continue;
            }
            $finishes[] = $task->finish($calendar, $calculation->virtualStarts[$id] ?? null)->format('Y-m-d');
        }

        return ['calculation' => $calculation, 'projections' => $sectionCalculation['projections'], 'dependencies' => $sectionCalculation['dependencies'], 'sectionParents' => $sectionParents, 'projectFinish' => $finishes === [] ? null : max($finishes)];
    }

    /**
     * @param  array{calculation: ScheduleResult, projections: array<string, TaskProjection>, projectFinish: string|null}  $before
     * @param  array{calculation: ScheduleResult, projections: array<string, TaskProjection>, projectFinish: string|null}  $after
     * @return array{affectedTasks: list<string>, projectFinishBefore: string|null, projectFinishAfter: string|null, enteredCriticalPath: list<string>, leftCriticalPath: list<string>, newConstraintViolations: list<string>, severity: string}
     */
    public function compare(array $before, array $after): array
    {
        $affected = [];
        $violations = [];
        foreach ($after['projections'] as $id => $projection) {
            $previous = $before['projections'][$id] ?? null;
            if ($previous === null || $previous->consideredStart != $projection->consideredStart || $previous->consideredDeadline != $projection->consideredDeadline) {
                $affected[] = $id;
            }
            if (($previous === null || $previous->scheduleConstraintState->value === 'satisfied') && $projection->scheduleConstraintState->value !== 'satisfied') {
                $violations[] = $id;
            }
        }
        $beforeCritical = array_fill_keys($before['calculation']->criticalTaskIds, true);
        $afterCritical = array_fill_keys($after['calculation']->criticalTaskIds, true);
        $entered = array_values(array_diff(array_keys($afterCritical), array_keys($beforeCritical)));
        $left = array_values(array_diff(array_keys($beforeCritical), array_keys($afterCritical)));
        $projectDelayed = $before['projectFinish'] !== null && $after['projectFinish'] !== null && $after['projectFinish'] > $before['projectFinish'];
        $lostFloat = [];
        foreach ($after['calculation']->totalFloat as $id => $float) {
            if ($float === 0 && ($before['calculation']->totalFloat[$id] ?? 0) > 0) {
                $lostFloat[] = $id;
            }
        }
        $severity = $projectDelayed || $violations !== []
            ? 'critical'
            : ($entered !== [] || $lostFloat !== [] ? 'warning' : 'informational');

        return [
            'affectedTasks' => $affected,
            'projectFinishBefore' => $before['projectFinish'],
            'projectFinishAfter' => $after['projectFinish'],
            'enteredCriticalPath' => $entered,
            'leftCriticalPath' => $left,
            'newConstraintViolations' => $violations,
            'severity' => $severity,
        ];
    }
}
