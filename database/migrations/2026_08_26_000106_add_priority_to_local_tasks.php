<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('project_tasks', 'priority')) {
            return;
        }
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('priority')->default(1)->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('project_tasks', 'priority')) {
            return;
        }
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
