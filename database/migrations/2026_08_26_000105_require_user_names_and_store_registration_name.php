<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('name')->update(['name' => 'Rodrigo Leitão', 'updated_at' => now()]);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
        Schema::table('login_challenges', function (Blueprint $table): void {
            $table->string('registration_name')->nullable()->after('email');
        });
        DB::table('project_people')
            ->whereNotNull('linked_user_id')
            ->update([
                'name' => DB::raw('(select name from users where users.id = project_people.linked_user_id)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('login_challenges', function (Blueprint $table): void {
            $table->dropColumn('registration_name');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });
    }
};
