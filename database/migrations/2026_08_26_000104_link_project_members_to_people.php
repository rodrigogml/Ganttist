<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $members = DB::table('project_members')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->leftJoin('project_people', function ($join): void {
                $join->on('project_people.project_id', '=', 'project_members.project_id')
                    ->on('project_people.linked_user_id', '=', 'project_members.user_id');
            })
            ->whereNull('project_people.id')
            ->get(['project_members.project_id', 'project_members.user_id', 'users.name', 'users.email']);

        foreach ($members as $member) {
            DB::table('project_people')->insert([
                'id' => (string) Str::ulid(),
                'project_id' => $member->project_id,
                'linked_user_id' => $member->user_id,
                'name' => $member->name ?: $member->email ?: 'Usuário',
                'email' => $member->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('project_people', function (Blueprint $table): void {
            $table->unique(['project_id', 'linked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('project_people', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'linked_user_id']);
        });
    }
};
