<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedule_dependencies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('predecessor_kind', ['task', 'section']);
            $table->ulid('predecessor_id');
            $table->enum('successor_kind', ['task', 'section']);
            $table->ulid('successor_id');
            $table->enum('type', ['FS', 'SS', 'FF', 'SF']);
            $table->timestamps();
            $table->unique(['project_id', 'predecessor_kind', 'predecessor_id', 'successor_kind', 'successor_id', 'type'], 'project_schedule_dependency_unique');
            $table->index(['project_id', 'predecessor_kind', 'predecessor_id'], 'psd_predecessor_lookup');
            $table->index(['project_id', 'successor_kind', 'successor_id'], 'psd_successor_lookup');
        });

        if (Schema::hasTable('project_task_dependencies')) {
            DB::table('project_task_dependencies')->orderBy('id')->each(function (object $dependency): void {
                DB::table('project_schedule_dependencies')->insert([
                    'id' => $dependency->id,
                    'project_id' => $dependency->project_id,
                    'predecessor_kind' => 'task',
                    'predecessor_id' => $dependency->predecessor_task_id,
                    'successor_kind' => 'task',
                    'successor_id' => $dependency->successor_task_id,
                    'type' => $dependency->type,
                    'created_at' => $dependency->created_at,
                    'updated_at' => $dependency->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_dependencies');
    }
};
