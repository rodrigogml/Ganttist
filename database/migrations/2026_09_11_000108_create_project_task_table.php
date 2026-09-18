<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_taskTable', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('idProject')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUlid('idTask')->constrained('project_tasks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUlid('idPublishedByUser')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->json('document');
            $table->unsignedInteger('documentVersion')->default(1);
            $table->foreignUlid('idEditLockUser')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('editLockTokenHash', 64)->nullable();
            $table->timestamp('editLockExpiresAt')->nullable();
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');
            $table->index(['idTask', 'createdAt'], 'idx_task_table_created');
            $table->index(['idProject', 'idTask'], 'idx_project_task_table');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_taskTable');
    }
};
