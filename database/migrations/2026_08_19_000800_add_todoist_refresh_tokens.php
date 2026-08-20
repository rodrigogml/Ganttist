<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->text('refresh_token_encrypted')->nullable()->after('access_token_encrypted');
            $table->timestamp('access_token_expires_at')->nullable()->after('refresh_token_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->dropColumn(['refresh_token_encrypted', 'access_token_expires_at']);
        });
    }
};
