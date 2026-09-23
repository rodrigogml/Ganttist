<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_views', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('query');
            $table->json('visual_state');
            $table->unsignedSmallInteger('format_version')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'owner_user_id', 'name'], 'project_task_view_owner_name_unique');
            $table->index(['project_id', 'owner_user_id', 'updated_at'], 'project_task_view_owner_updated_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_views');
    }
};
