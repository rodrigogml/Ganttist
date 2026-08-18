<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recalculation_items', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(0)->after('recalculation_id');
            $table->index(['recalculation_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('recalculation_items', function (Blueprint $table) {
            $table->dropIndex(['recalculation_id', 'sequence']);
            $table->dropColumn('sequence');
        });
    }
};
