<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todoist_oauth_states', function (Blueprint $table): void {
            $table->timestamp('consumed_at')->nullable()->after('expires_at');
            $table->index(['expires_at', 'consumed_at']);
        });

        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->timestamp('token_rotated_at')->nullable()->after('authorized_at');
        });
    }

    public function down(): void
    {
        Schema::table('todoist_oauth_states', function (Blueprint $table): void {
            $table->dropIndex(['expires_at', 'consumed_at']);
            $table->dropColumn('consumed_at');
        });

        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->dropColumn('token_rotated_at');
        });
    }
};
