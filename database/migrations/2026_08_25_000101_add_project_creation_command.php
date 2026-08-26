<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('creation_command_id', 64)->after('name');
            $table->unique(['owner_user_id', 'creation_command_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['owner_user_id', 'creation_command_id']);
            $table->dropColumn('creation_command_id');
        });
    }
};
