<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHECK_NAME = 'chk_project_tasks_planned_duration_workdays';

    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('plannedDurationWorkdays')->nullable()->after('planned_finish');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_tasks ADD CONSTRAINT '.self::CHECK_NAME.' CHECK (`plannedDurationWorkdays` IS NULL OR `plannedDurationWorkdays` BETWEEN 1 AND 3650)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_tasks DROP CHECK '.self::CHECK_NAME);
        }

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropColumn('plannedDurationWorkdays');
        });
    }
};
