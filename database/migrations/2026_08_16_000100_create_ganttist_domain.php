<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('todoist_integrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('todoist_user_id')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_token')->nullable();
            $table->timestamps();
        });

        Schema::create('gantt_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('todoist_project_id');
            $table->string('display_name');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'todoist_project_id']);
        });

        Schema::create('project_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('rescheduling_mode', ['MANUAL', 'AUTOMATIC'])->default('MANUAL');
            $table->enum('non_working_deadline_policy', ['ANTERIOR', 'POSTERIOR'])->default('ANTERIOR');
            $table->boolean('allow_unscheduled_tasks')->default(true);
            $table->boolean('show_non_working_days')->default(true);
            $table->string('default_scale', 16)->default('week');
            $table->boolean('monday')->default(true);
            $table->boolean('tuesday')->default(true);
            $table->boolean('wednesday')->default(true);
            $table->boolean('thursday')->default(true);
            $table->boolean('friday')->default(true);
            $table->boolean('saturday')->default(false);
            $table->boolean('sunday')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('calendar_exceptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['NON_WORKING', 'WORKING']);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['gantt_project_id', 'date']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->constrained()->cascadeOnDelete();
            $table->string('predecessor_todoist_task_id');
            $table->string('successor_todoist_task_id');
            $table->enum('type', ['FS', 'SS', 'FF', 'SF']);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['gantt_project_id', 'predecessor_todoist_task_id', 'successor_todoist_task_id', 'type'], 'task_dependency_unique');
            $table->index(['gantt_project_id', 'successor_todoist_task_id'], 'task_dep_project_successor_idx');
        });

        Schema::create('task_metadata', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->constrained()->cascadeOnDelete();
            $table->string('todoist_task_id');
            $table->date('completion_date_override')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['gantt_project_id', 'todoist_task_id']);
        });

        Schema::create('recalculations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->constrained()->cascadeOnDelete();
            $table->string('command_id', 64)->unique();
            $table->enum('mode', ['MANUAL', 'AUTOMATIC']);
            $table->string('state', 24)->default('pending');
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('recalculation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recalculation_id')->constrained()->cascadeOnDelete();
            $table->string('todoist_task_id');
            $table->json('before_state');
            $table->json('after_state');
            $table->string('state', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('gantt_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('command_id', 64)->unique();
            $table->string('operation', 64);
            $table->string('state', 24)->default('pending');
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['state', 'available_at']);
        });

        Schema::create('todoist_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('external_event_id')->unique();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('gantt_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('origin', 24);
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id')->nullable();
            $table->string('causation_id', 64)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->timestamps();
            $table->index(['gantt_project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'todoist_events', 'sync_operations', 'recalculation_items', 'recalculations', 'task_metadata', 'task_dependencies', 'calendar_exceptions', 'project_settings', 'gantt_projects', 'todoist_integrations', 'login_challenges'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
