<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todoist_oauth_states', function (Blueprint $table): void {
            $table->boolean('remember')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('todoist_oauth_states', function (Blueprint $table): void {
            $table->dropColumn('remember');
        });
    }
};
