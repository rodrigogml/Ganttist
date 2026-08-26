<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'audit_events', 'todoist_events', 'sync_operations', 'recalculation_items', 'recalculations',
            'task_metadata', 'task_dependencies', 'calendar_exceptions', 'project_settings',
            'gantt_projects', 'todoist_integrations', 'todoist_oauth_states',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index(['owner_user_id', 'updated_at']);
        });

        Schema::create('project_sections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('parent_section_id')->nullable()->constrained('project_sections')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'parent_section_id', 'position']);
        });

        Schema::create('project_people', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'name']);
        });

        Schema::create('project_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('project_sections')->cascadeOnDelete();
            $table->foreignUlid('assignee_person_id')->nullable()->constrained('project_people')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->date('planned_start')->nullable();
            $table->date('planned_finish')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'section_id', 'position']);
            $table->index(['project_id', 'planned_finish', 'completed_at']);
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'editor', 'reader']);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['editor', 'reader']);
            $table->enum('status', ['pending', 'accepted', 'declined', 'revoked', 'expired'])->default('pending');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['email', 'status']);
        });

        Schema::create('project_task_dependencies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('predecessor_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignUlid('successor_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->enum('type', ['FS', 'SS', 'FF', 'SF']);
            $table->timestamps();
            $table->unique(['project_id', 'predecessor_task_id', 'successor_task_id', 'type'], 'project_task_dependency_unique');
        });
    }

    public function down(): void
    {
        foreach (['project_task_dependencies', 'project_invitations', 'project_members', 'project_tasks', 'project_people', 'project_sections', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
