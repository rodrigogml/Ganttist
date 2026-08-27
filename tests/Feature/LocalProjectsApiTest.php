<?php

namespace Tests\Feature;

use App\Mail\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class LocalProjectsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creates_and_lists_a_local_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'create-product'])->assertCreated()->assertJsonPath('data.name', 'Produto')->assertJsonPath('data.role', 'owner');
        $this->actingAs($user)->getJson('/api/v1/projects')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_workspace_lists_members_as_assignable_people(): void
    {
        $owner = User::factory()->create(['name' => 'Pessoa Proprietária']);
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'owner-assignee'])->json('data.id');

        $workspace = $this->actingAs($owner)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.people.0.name', 'Pessoa Proprietária');
        $personId = $workspace->json('data.people.0.id');

        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/tasks", [
            'title' => 'Tarefa atribuída à proprietária',
            'assigneePersonId' => $personId,
        ])->assertCreated();
    }

    public function test_reader_cannot_create_project_structure(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'create-product'])->json('data.id');
        \DB::table('project_members')->insert(['id' => (string) \Str::ulid(), 'project_id' => $project, 'user_id' => $reader->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($reader)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Planejamento'])->assertForbidden();
    }

    public function test_invited_user_only_gains_access_after_accepting(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $guest = User::factory()->create(['email' => 'guest@example.com']);
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'create-product'])->json('data.id');
        $invitation = $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/invitations", ['email' => $guest->email, 'role' => 'reader'])->assertCreated()->json('data.id');
        Mail::assertSent(ProjectInvitation::class, fn (ProjectInvitation $mail): bool => $mail->hasTo($guest->email) && $mail->projectName === 'Produto' && $mail->role === 'reader');
        $this->actingAs($guest)->getJson("/api/v1/projects/{$project}/workspace")->assertNotFound();
        $this->actingAs($guest)->postJson("/api/v1/invitations/{$invitation}/accept")->assertOk();
        $this->actingAs($guest)->getJson("/api/v1/projects/{$project}/workspace")->assertOk();
    }

    public function test_workspace_preserves_section_hierarchy_and_calculates_task_statuses(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'workspace'])->json('data.id');
        $parent = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Planejamento'])->assertCreated()->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Pesquisa', 'parentSectionId' => $parent])->assertCreated()->json('data.id');
        $predecessor = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Preparar', 'sectionId' => $child])->assertCreated()->json('data.id');
        $successor = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Executar', 'plannedFinish' => now()->subDay()->toDateString()])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/dependencies", ['from' => $predecessor, 'to' => $successor, 'type' => 'FS'])->assertCreated();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.1.level', 1)
            ->assertJsonPath('data.stats.total', 2)
            ->assertJsonPath('data.tasks.3.status', 'blocked');
    }

    public function test_workspace_projects_open_fs_predecessors_into_successive_unlock_dates(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'unlock-projection'])->json('data.id');
        $first = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Primeira', 'plannedStart' => '2026-09-07', 'plannedFinish' => '2026-09-08'])->json('data.id');
        $second = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Segunda'])->json('data.id');
        $third = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Terceira'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/dependencies", ['from' => $first, 'to' => $second, 'type' => 'FS'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/dependencies", ['from' => $second, 'to' => $third, 'type' => 'FS'])->assertCreated();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.1.unlock_date', '2026-09-09')
            ->assertJsonPath('data.tasks.1.considered_start', '2026-09-09')
            ->assertJsonPath('data.tasks.2.unlock_date', '2026-09-10')
            ->assertJsonPath('data.tasks.2.considered_start', '2026-09-10');
    }

    public function test_workspace_exposes_the_local_critical_path_for_tasks_sections_and_dependencies(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'critical-path'])->json('data.id');
        $section = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Entrega'])->json('data.id');
        $first = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Primeira', 'sectionId' => $section, 'plannedStart' => '2026-09-07', 'plannedFinish' => '2026-09-08'])->json('data.id');
        $second = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Segunda', 'sectionId' => $section, 'plannedStart' => '2026-09-09', 'plannedFinish' => '2026-09-10'])->json('data.id');
        $parallel = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Paralela', 'plannedStart' => '2026-09-07', 'plannedFinish' => '2026-09-07'])->json('data.id');
        $dependency = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/dependencies", ['from' => $first, 'to' => $second, 'type' => 'FS'])->assertCreated()->json('data.id');

        $workspace = $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.stats.critical', 2)
            ->json('data');
        $tasks = collect($workspace['tasks'])->keyBy('id');
        $dependencies = collect($workspace['dependencies'])->keyBy('id');

        $this->assertTrue($tasks[$section]['critical']);
        $this->assertTrue($tasks[$first]['critical']);
        $this->assertTrue($tasks[$second]['critical']);
        $this->assertFalse($tasks[$parallel]['critical']);
        $this->assertSame(0, $tasks[$first]['total_float']);
        $this->assertTrue($dependencies[$dependency]['critical']);
    }

    public function test_workspace_derives_section_ranges_from_all_descendant_tasks_and_leaves_empty_sections_undated(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'section-ranges'])->json('data.id');
        $parent = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Planejamento'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Entrega', 'parentSectionId' => $parent])->json('data.id');
        $empty = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Vazia'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Início', 'sectionId' => $parent, 'plannedStart' => '2026-09-07', 'plannedFinish' => '2026-09-08'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Fim', 'sectionId' => $child, 'plannedStart' => '2026-09-14', 'plannedFinish' => '2026-09-16'])->assertCreated();

        $tasks = $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")->assertOk()->json('data.tasks');
        $byId = collect($tasks)->keyBy('id');
        $this->assertSame('2026-09-07', $byId[$parent]['considered_start']);
        $this->assertSame('2026-09-16', $byId[$parent]['considered_deadline']);
        $this->assertSame('2026-09-14', $byId[$child]['considered_start']);
        $this->assertSame('2026-09-16', $byId[$child]['considered_deadline']);
        $this->assertArrayNotHasKey('considered_start', $byId[$empty]);
        $this->assertArrayNotHasKey('considered_deadline', $byId[$empty]);
    }

    public function test_section_can_be_moved_to_root_and_workspace_keeps_groups_together(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'section-root'])->json('data.id');
        $parent = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Pai'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Filha', 'parentSectionId' => $parent])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Tarefa da filha', 'sectionId' => $child])->assertCreated();

        $this->actingAs($user)->putJson("/api/v1/projects/{$project}/sections/{$child}", ['name' => 'Filha', 'parentSectionId' => null])->assertOk();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.id', $parent)
            ->assertJsonPath('data.tasks.1.id', $child)
            ->assertJsonPath('data.tasks.2.title', 'Tarefa da filha')
            ->assertJsonPath('data.tasks.2.level', 1);
    }

    public function test_structure_move_reorders_tasks_and_rejects_section_cycles(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'structure-move'])->json('data.id');
        $first = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Primeira'])->json('data.id');
        $second = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Segunda'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Filha', 'parentSectionId' => $first])->json('data.id');
        $task = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Reorganizar', 'sectionId' => $first])->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/structure/move", ['itemId' => $task, 'itemKind' => 'task', 'parentSectionId' => null, 'beforeItemId' => $second])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/structure/move", ['itemId' => $first, 'itemKind' => 'section', 'parentSectionId' => $child])->assertUnprocessable();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.id', $first)
            ->assertJsonPath('data.tasks.1.id', $child)
            ->assertJsonPath('data.tasks.2.id', $task)
            ->assertJsonPath('data.tasks.3.id', $second);
    }

    public function test_task_duplication_copies_details_comments_and_dependencies(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'duplicate-task'])->json('data.id');
        $original = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Original', 'description' => 'Detalhes', 'priority' => 3, 'actualCompletionDate' => '2026-08-26'])->json('data.id');
        $other = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Outra'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks/{$original}/comments", ['content' => 'Comentário'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/dependencies", ['from' => $original, 'to' => $other, 'type' => 'FS'])->assertCreated();

        $copy = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks/{$original}/duplicate")->assertCreated()->json('data.id');

        $this->assertDatabaseHas('project_tasks', ['id' => $copy, 'title' => 'Original - Copia', 'priority' => 3, 'completed_at' => '2026-08-26']);
        $this->assertDatabaseHas('project_task_comments', ['task_id' => $copy, 'content' => 'Comentário']);
        $this->assertDatabaseHas('project_task_dependencies', ['predecessor_task_id' => $copy, 'successor_task_id' => $other, 'type' => 'FS']);
    }

    public function test_section_deletion_can_move_direct_children_to_root(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'move-section-children'])->json('data.id');
        $section = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Remover'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Filha', 'parentSectionId' => $section])->json('data.id');
        $task = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Direta', 'sectionId' => $section])->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/sections/{$section}", ['action' => 'move', 'destinationSectionId' => null])->assertNoContent();

        $this->assertDatabaseHas('project_sections', ['id' => $child, 'parent_section_id' => null]);
        $this->assertDatabaseHas('project_tasks', ['id' => $task, 'section_id' => null]);
    }

    public function test_removing_a_person_unassigns_their_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'people'])->json('data.id');
        $person = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/people", ['name' => 'Ana'])->assertCreated()->json('data.id');
        $task = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Delegada', 'assigneePersonId' => $person])->assertCreated()->json('data.id');
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/people/{$person}")->assertNoContent();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.id', $task)
            ->assertJsonPath('data.tasks.0.assignee_id', null);
    }

    public function test_editor_can_manage_project_people_without_gaining_member_access(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'person-management'])->json('data.id');
        \DB::table('project_members')->insert(['id' => (string) \Str::ulid(), 'project_id' => $project, 'user_id' => $editor->id, 'role' => 'editor', 'created_at' => now(), 'updated_at' => now()]);
        $person = $this->actingAs($editor)->postJson("/api/v1/projects/{$project}/people", ['name' => 'Ana', 'email' => 'ana@example.com'])->assertCreated()->json('data.id');
        $this->actingAs($editor)->putJson("/api/v1/projects/{$project}/people/{$person}", ['name' => 'Ana Silva', 'email' => null])->assertOk()->assertJsonPath('data.name', 'Ana Silva');
        $this->actingAs($editor)->getJson("/api/v1/projects/{$project}/members")->assertOk()->assertJsonPath('data.people.0.name', 'Ana Silva');
        $this->assertDatabaseMissing('project_members', ['project_id' => $project, 'user_id' => $editor->id, 'role' => 'owner']);
    }

    public function test_owner_can_invite_a_responsible_and_block_them_with_the_pending_access(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'responsible-access'])->json('data.id');

        $person = $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/people", ['name' => 'Ana', 'email' => 'ana@example.com', 'accessRole' => 'editor'])
            ->assertCreated()
            ->json('data.id');

        Mail::assertSent(ProjectInvitation::class, fn (ProjectInvitation $mail): bool => $mail->hasTo('ana@example.com') && $mail->role === 'editor');
        $invitation = \DB::table('project_invitations')->where('project_id', $project)->where('email', 'ana@example.com')->first();
        $this->assertNotNull($invitation->last_sent_at);
        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/invitations/{$invitation->id}/resend")->assertStatus(429);

        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/people/{$person}/block")->assertNoContent();

        $this->assertDatabaseMissing('project_people', ['id' => $person, 'blocked_at' => null]);
        $this->assertDatabaseHas('project_invitations', ['id' => $invitation->id, 'status' => 'revoked']);
    }

    public function test_editor_can_create_edit_and_delete_a_local_comment(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'comments'])->json('data.id');
        $task = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Tarefa'])->json('data.id');
        $comment = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks/{$task}/comments", ['content' => 'Primeira nota'])->assertCreated()->json('data.id');
        $this->actingAs($user)->putJson("/api/v1/projects/{$project}/tasks/{$task}/comments/{$comment}", ['content' => 'Nota revisada'])->assertOk();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/tasks/{$task}/context")
            ->assertOk()
            ->assertJsonPath('data.comments.0.content', 'Nota revisada')
            ->assertJsonPath('data.comments.0.author_name', $user->name)
            ->assertJsonPath('data.comments.0.editable', true);

        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/tasks/{$task}/comments/{$comment}")->assertNoContent();
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/tasks/{$task}/context")
            ->assertOk()
            ->assertJsonCount(0, 'data.comments');
    }

    public function test_only_owner_can_manage_members_or_delete_project(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'roles'])->json('data.id');
        $memberId = (string) \Str::ulid();
        \DB::table('project_members')->insert(['id' => $memberId, 'project_id' => $project, 'user_id' => $editor->id, 'role' => 'editor', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($editor)->getJson("/api/v1/projects/{$project}/members")->assertOk()->assertJsonCount(2, 'data.members');
        $this->actingAs($editor)->deleteJson("/api/v1/projects/{$project}")->assertForbidden();
        $this->actingAs($owner)->putJson("/api/v1/projects/{$project}/members/{$memberId}", ['role' => 'reader'])->assertOk();
        $this->actingAs($owner)->deleteJson("/api/v1/projects/{$project}")->assertNoContent();
    }

    public function test_user_sees_only_their_pending_invitations(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create(['email' => 'guest@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $project = $this->actingAs($owner)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'inbox'])->json('data.id');
        $this->actingAs($owner)->postJson("/api/v1/projects/{$project}/invitations", ['email' => $guest->email, 'role' => 'reader'])->assertCreated();
        $this->actingAs($guest)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.project_name', 'Produto');
        $this->actingAs($other)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_deleting_a_section_cascades_to_child_sections_and_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'section-delete'])->json('data.id');
        $parent = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Pai'])->json('data.id');
        $child = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Filha', 'parentSectionId' => $parent])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Filha', 'sectionId' => $child])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Raiz'])->assertCreated();
        $this->actingAs($user)->deleteJson("/api/v1/projects/{$project}/sections/{$parent}")->assertNoContent();
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")->assertOk()->assertJsonCount(1, 'data.tasks')->assertJsonPath('data.tasks.0.title', 'Raiz');
    }

    public function test_actual_completion_date_is_saved_and_drives_completed_status(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'actual-completion'])->json('data.id');
        $task = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Entrega'])->json('data.id');
        $this->actingAs($user)->putJson("/api/v1/projects/{$project}/tasks/{$task}", ['actualCompletionDate' => '2026-08-20'])->assertOk();
        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")->assertOk()->assertJsonPath('data.tasks.0.effective_completion', '2026-08-20')->assertJsonPath('data.tasks.0.status', 'completed');
    }

    public function test_task_creation_accepts_all_local_task_fields(): void
    {
        $user = User::factory()->create();
        $project = $this->actingAs($user)->postJson('/api/v1/projects', ['name' => 'Produto', 'commandId' => 'task-fields'])->json('data.id');
        $person = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/people", ['name' => 'Ana'])->json('data.id');
        $section = $this->actingAs($user)->postJson("/api/v1/projects/{$project}/sections", ['name' => 'Planejamento'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/projects/{$project}/tasks", ['title' => 'Entrega', 'description' => 'Detalhes', 'sectionId' => $section, 'assigneePersonId' => $person, 'plannedStart' => '2026-08-26', 'plannedFinish' => '2026-08-28', 'actualCompletionDate' => '2026-08-28'])->assertCreated();

        $this->actingAs($user)->getJson("/api/v1/projects/{$project}/workspace")
            ->assertOk()
            ->assertJsonPath('data.tasks.1.description', 'Detalhes')
            ->assertJsonPath('data.tasks.1.assignee_id', $person)
            ->assertJsonPath('data.tasks.1.effective_completion', '2026-08-28');
    }
}
