<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_people', function (Blueprint $table): void {
            $table->timestamp('blocked_at')->nullable()->after('email');
            $table->index(['project_id', 'blocked_at']);
        });

        Schema::table('project_invitations', function (Blueprint $table): void {
            $table->timestamp('last_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_invitations', function (Blueprint $table): void {
            $table->dropColumn('last_sent_at');
        });

        Schema::table('project_people', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'blocked_at']);
            $table->dropColumn('blocked_at');
        });
    }
};
