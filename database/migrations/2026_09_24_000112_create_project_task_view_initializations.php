<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_view_initializations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'owner_user_id'], 'project_task_view_init_project_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_view_initializations');
    }
};
