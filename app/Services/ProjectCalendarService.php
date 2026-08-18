<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scheduling\ProjectCalendar;
use App\Domain\Scheduling\WorkCalendar;
use Illuminate\Support\Facades\DB;

final class ProjectCalendarService
{
    public function forProject(string $projectId): WorkCalendar
    {
        $settings = DB::table('project_settings')->where('gantt_project_id', $projectId)->first();
        if ($settings === null) {
            return new WorkCalendar;
        }

        return ProjectCalendar::fromSettings(
            $settings,
            DB::table('calendar_exceptions')->where('gantt_project_id', $projectId)->get(),
        );
    }
}
