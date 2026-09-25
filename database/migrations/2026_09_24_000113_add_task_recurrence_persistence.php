<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RECURRENCE_PAIR_CHECK = 'chk_project_tasks_recurrence_pair';

    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->json('recurrenceRule')->nullable()->after('plannedDurationWorkdays');
            $table->date('recurrenceCursor')->nullable()->after('recurrenceRule');
            $table->unsignedInteger('recurrenceVersion')->default(0)->after('recurrenceCursor');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_tasks ADD CONSTRAINT '.self::RECURRENCE_PAIR_CHECK.' CHECK ((`recurrenceRule` IS NULL AND `recurrenceCursor` IS NULL) OR (`recurrenceRule` IS NOT NULL AND `recurrenceCursor` IS NOT NULL))');
        }

        Schema::create('projectTaskOccurrence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('projectId');
            $table->ulid('taskId');
            $table->date('logicalDate');
            $table->date('scheduledStart')->nullable();
            $table->date('scheduledFinish')->nullable();
            $table->date('completedAt');
            $table->ulid('completedByUserId')->nullable();
            $table->unsignedInteger('recurrenceVersion');
            $table->json('recurrenceRuleSnapshot');
            $table->uuid('completionCommandId');
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');

            $table->foreign('projectId', 'fk_occurrence_project')->references('id')->on('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('taskId', 'fk_occurrence_task')->references('id')->on('project_tasks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('completedByUserId', 'fk_occurrence_completed_by_user')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['taskId', 'logicalDate'], 'uk_occurrence_task_logical');
            $table->unique('completionCommandId', 'uk_occurrence_command');
            $table->index(['projectId', 'taskId', 'completedAt'], 'idx_occurrence_project_task_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projectTaskOccurrence');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_tasks DROP CHECK '.self::RECURRENCE_PAIR_CHECK);
        }

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropColumn(['recurrenceRule', 'recurrenceCursor', 'recurrenceVersion']);
        });
    }
};
