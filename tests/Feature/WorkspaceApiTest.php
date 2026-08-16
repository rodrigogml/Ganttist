<?php

namespace Tests\Feature;

use Tests\TestCase;

final class WorkspaceApiTest extends TestCase
{
    public function test_workspace_contract_contains_hierarchy_and_dependencies(): void
    {
        $this->getJson('/api/v1/workspace')->assertOk()->assertJsonPath('data.project.source', 'Todoist')->assertJsonCount(12, 'data.tasks')->assertJsonCount(6, 'data.dependencies');
    }

    public function test_schedule_simulation_validates_and_returns_changes(): void
    {
        $this->postJson('/api/v1/schedule/simulate', ['today' => '2026-08-16', 'tasks' => [
            ['id' => 'A', 'title' => 'A', 'start' => '2026-08-20', 'duration' => 2], ['id' => 'B', 'title' => 'B', 'start' => '2026-08-21', 'duration' => 1],
        ], 'dependencies' => [['from' => 'A', 'to' => 'B', 'type' => 'FS']]])->assertOk()->assertJsonPath('data.changes.0.start', '2026-08-24');
    }
}
