<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projectTaskChecklistItem', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('projectId')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUlid('taskId')->constrained('project_tasks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('text', 1000);
            $table->boolean('isCompleted')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');
            $table->index(['taskId', 'position'], 'idx_checklist_task_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projectTaskChecklistItem');
    }
};
