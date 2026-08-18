<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scheduling\ProjectCalendar;
use App\Domain\Scheduling\SchedulingEngine;

final readonly class CalendarSimulationService
{
    public function __construct(private AuthorizedPlanningSnapshot $snapshots) {}

    /** @param array{workingDays: list<string>, exceptions: list<array{date: string, type: string, description?: string|null}>} $settings */
    public function simulate(object $project, object $integration, array $settings): array
    {
        $snapshot = $this->snapshots->load($project, $integration);
        $today = now()->startOfDay()->toDateTimeImmutable();
        $before = (new SchedulingEngine($snapshot['calendar']))->schedule(array_values($snapshot['tasks']), $snapshot['dependencies'], $today);
        $week = array_fill_keys($settings['workingDays'], true);
        $candidateCalendar = ProjectCalendar::fromSettings([
            'monday' => isset($week['monday']), 'tuesday' => isset($week['tuesday']), 'wednesday' => isset($week['wednesday']),
            'thursday' => isset($week['thursday']), 'friday' => isset($week['friday']), 'saturday' => isset($week['saturday']), 'sunday' => isset($week['sunday']),
        ], $settings['exceptions']);
        $after = (new SchedulingEngine($candidateCalendar))->schedule(array_values($snapshot['tasks']), $snapshot['dependencies'], $today);
        $changes = [];
        foreach ($snapshot['tasks'] as $id => $originalTask) {
            if ($originalTask->completed || $originalTask->start === null) {
                continue;
            }
            $beforeTask = $before->tasks[$id];
            $afterTask = $after->tasks[$id];
            $beforeState = ['start' => $beforeTask->start?->format('Y-m-d'), 'finish' => $beforeTask->finish($snapshot['calendar'])->format('Y-m-d')];
            $afterState = ['start' => $afterTask->start?->format('Y-m-d'), 'finish' => $afterTask->finish($candidateCalendar)->format('Y-m-d')];
            if ($beforeState !== $afterState) {
                $changes[] = ['task_id' => $id, 'before' => $beforeState, 'after' => $afterState];
            }
        }

        return compact('snapshot', 'candidateCalendar', 'changes');
    }
}
