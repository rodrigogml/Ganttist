<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settings', function (Blueprint $table): void {
            // Keep the original deployed shape immutable. A later migration
            // normalizes these legacy names without losing existing values.
            $table->boolean('autoScheduleBlockedTasks')->default(false)->after('projection_policy');
            $table->unsignedInteger('automationVersion')->default(1)->after('autoScheduleBlockedTasks');
        });
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table): void {
            $table->dropColumn(['autoScheduleBlockedTasks', 'automationVersion']);
        });
    }
};
