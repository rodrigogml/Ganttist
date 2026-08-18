<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->string('sync_state', 32)->default('unknown')->after('status');
            $table->string('last_sync_error', 64)->nullable()->after('sync_state');
        });
    }

    public function down(): void
    {
        Schema::table('todoist_integrations', function (Blueprint $table): void {
            $table->dropColumn(['sync_state', 'last_sync_error']);
        });
    }
};
