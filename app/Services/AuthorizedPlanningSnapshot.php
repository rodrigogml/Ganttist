<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TodoistGateway;
use App\Domain\Scheduling\Dependency;
use App\Domain\Scheduling\TaskPlan;
use App\Domain\Scheduling\WorkCalendar;
use App\Support\TodoistTask;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class AuthorizedPlanningSnapshot
{
    public function __construct(private TodoistGateway $gateway, private ProjectCalendarService $calendars, private TodoistAccessTokenService $tokens) {}

    /** @return array{tasks: array<string, TaskPlan>, dependencies: list<Dependency>, calendar: WorkCalendar} */
    public function load(object $project, object $integration): array
    {
        $calendar = $this->calendars->forProject($project->id);
        $snapshot = $this->gateway->projectSnapshot($this->tokens->accessToken($integration), $project->todoist_project_id);
        $sourceTasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
        $knownIds = array_fill_keys(array_map(fn (array $task): string => (string) $task['id'], $sourceTasks), true);
        $completionOverrides = DB::table('task_metadata')->where('gantt_project_id', $project->id)->whereNotNull('completion_date_override')->pluck('completion_date_override', 'todoist_task_id')->all();
        $tasks = [];
        foreach ($sourceTasks as $task) {
            $id = (string) $task['id'];
            $startDate = TodoistTask::start($task);
            $finishDate = TodoistTask::finish($task);
            $start = $startDate ? new DateTimeImmutable($startDate) : null;
            $deadline = $finishDate ? new DateTimeImmutable($finishDate) : null;
            $parentId = isset($task['parent_id']) && isset($knownIds[(string) $task['parent_id']]) ? (string) $task['parent_id'] : null;
            $tasks[$id] = TaskPlan::fromDates($id, (string) $task['content'], $start, $deadline, $calendar, TodoistTask::completed($task), isset($completionOverrides[$id]) ? new DateTimeImmutable($completionOverrides[$id]) : null, $parentId);
        }
        $dependencies = [];
        foreach (DB::table('task_dependencies')->where('gantt_project_id', $project->id)->where('status', 'active')->get() as $dependency) {
            if (isset($tasks[$dependency->predecessor_todoist_task_id], $tasks[$dependency->successor_todoist_task_id])) {
                $dependencies[] = new Dependency($dependency->predecessor_todoist_task_id, $dependency->successor_todoist_task_id, $dependency->type);
            }
        }

        return compact('tasks', 'dependencies', 'calendar');
    }
}
