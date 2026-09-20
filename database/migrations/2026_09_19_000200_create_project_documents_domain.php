<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['manual', 'derived'])->default('manual');
            $table->ulid('current_revision_id')->nullable();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'archived_at', 'name']);
            $table->index('current_revision_id');
        });

        Schema::create('project_tags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('parent_tag_id')->nullable()->constrained('project_tags')->restrictOnDelete();
            $table->char('scope_key', 26);
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'scope_key', 'normalized_name'], 'project_tag_scope_name_unique');
            $table->index(['project_id', 'parent_tag_id', 'archived_at']);
        });

        Schema::create('project_document_derivations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('source_document_id')->constrained('project_documents')->restrictOnDelete();
            $table->foreignUlid('target_document_id')->constrained('project_documents')->restrictOnDelete();
            $table->string('processor');
            $table->unsignedInteger('processor_version')->default(1);
            $table->unsignedInteger('definition_version')->default(1);
            $table->json('configuration');
            $table->char('configuration_hash', 64);
            $table->boolean('auto_regenerate')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('target_document_id');
            $table->index(['project_id', 'source_document_id', 'active', 'auto_regenerate'], 'project_derivation_source_active_idx');
        });

        Schema::create('project_document_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('project_documents')->cascadeOnDelete();
            $table->unsignedInteger('revision_sequence');
            $table->string('revision_label');
            $table->string('mime_type');
            $table->string('original_filename')->nullable();
            $table->string('storage_disk');
            $table->string('storage_key', 1024);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('source_revision_id')->nullable()->constrained('project_document_revisions')->restrictOnDelete();
            $table->foreignUlid('derivation_id')->nullable()->constrained('project_document_derivations')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'revision_sequence']);
            $table->index(['document_id', 'created_at']);
            $table->index(['derivation_id', 'source_revision_id'], 'project_revision_provenance_idx');
        });

        Schema::create('project_document_derivation_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('derivation_id')->constrained('project_document_derivations')->cascadeOnDelete();
            $table->foreignUlid('source_revision_id')->constrained('project_document_revisions')->restrictOnDelete();
            $table->unsignedInteger('definition_version');
            $table->foreignUlid('target_revision_id')->nullable()->constrained('project_document_revisions')->nullOnDelete();
            $table->enum('status', ['queued', 'processing', 'succeeded', 'failed'])->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['derivation_id', 'source_revision_id', 'definition_version'], 'project_derivation_run_idempotency');
            $table->index(['status', 'created_at']);
        });

        Schema::create('project_document_tag', function (Blueprint $table): void {
            $table->foreignUlid('document_id')->constrained('project_documents')->cascadeOnDelete();
            $table->foreignUlid('tag_id')->constrained('project_tags')->cascadeOnDelete();
            $table->primary(['document_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'project_document_tag',
            'project_document_derivation_runs',
            'project_document_revisions',
            'project_document_derivations',
            'project_tags',
            'project_documents',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
