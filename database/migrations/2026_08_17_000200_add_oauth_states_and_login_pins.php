<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_challenges', function (Blueprint $table): void {
            $table->string('pin_hash', 255)->nullable()->after('token_hash');
        });

        Schema::create('todoist_oauth_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('state_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todoist_oauth_states');
        Schema::table('login_challenges', function (Blueprint $table): void {
            $table->dropColumn('pin_hash');
        });
    }
};
