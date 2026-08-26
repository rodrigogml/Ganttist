<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->foreign('section_id')->references('id')->on('project_sections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->foreign('section_id')->references('id')->on('project_sections')->nullOnDelete();
        });
    }
};
