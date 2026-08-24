<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settings', function (Blueprint $table): void {
            $table->enum('projection_policy', ['PRESERVE_DURATION', 'PRESERVE_DEADLINE'])
                ->default('PRESERVE_DURATION')
                ->after('rescheduling_mode');
        });
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table): void {
            $table->dropColumn('projection_policy');
        });
    }
};
