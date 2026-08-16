<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create(['name' => 'Marina Gestora', 'email' => 'demo@ganttist.local']);
        $projectId = (string) Str::ulid();
        DB::table('gantt_projects')->insert(['id' => $projectId, 'user_id' => $user->id, 'todoist_project_id' => 'demo-product-launch', 'display_name' => 'Lançamento do Ganttist', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('project_settings')->insert(['id' => (string) Str::ulid(), 'gantt_project_id' => $projectId, 'created_at' => now(), 'updated_at' => now()]);
    }
}
