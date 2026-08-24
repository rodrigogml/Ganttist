<?php

declare(strict_types=1);

namespace App\Infrastructure\Todoist;

use App\Contracts\TodoistGateway;

final class FakeTodoistGateway implements TodoistGateway
{
    public function projects(string $accessToken): array
    {
        return ['results' => [['id' => 'fake-project', 'name' => 'Projeto de demonstração']]];
    }

    public function projectSnapshot(string $accessToken, string $projectId): array
    {
        return ['sections' => ['results' => [['id' => 'fake-section', 'name' => 'Entrega']]], 'collaborators' => ['results' => [['id' => 'fake-user', 'name' => 'Pessoa Exemplo', 'email' => 'pessoa@example.test']]], 'tasks' => ['results' => [
            ['id' => 'fake-group', 'content' => 'Preparação', 'description' => 'Descrição de agrupador que não deve aparecer na árvore.', 'parent_id' => null, 'section_id' => 'fake-section', 'is_completed' => false, 'priority' => 1, 'due' => null, 'deadline_date' => null],
            ['id' => 'fake-task-1', 'content' => 'Preparar primeira entrega', 'description' => 'Conferir o escopo acordado com o cliente.', 'parent_id' => 'fake-group', 'section_id' => 'fake-section', 'is_completed' => true, 'priority' => 2, 'due' => ['date' => '2026-08-17'], 'deadline_date' => '2026-08-19', 'note_count' => 2],
            ['id' => 'fake-task-2', 'content' => 'Validar resultado', 'description' => '', 'parent_id' => null, 'section_id' => 'fake-section', 'is_completed' => false, 'priority' => 1, 'due' => null, 'deadline_date' => null],
        ]]];
    }

    public function comments(string $accessToken, string $taskId): array
    {
        return ['results' => [['id' => 'fake-comment', 'task_id' => $taskId, 'posted_uid' => 'fake-user', 'content' => 'Comentário de exemplo', 'posted_at' => '2026-08-21T12:00:00Z']]];
    }

    public function createComment(string $accessToken, string $taskId, string $content): array
    {
        return ['id' => 'fake-created-comment', 'task_id' => $taskId, 'posted_uid' => 'fake-user', 'content' => $content, 'posted_at' => now()->toIso8601String()];
    }

    public function updateComment(string $accessToken, string $commentId, string $content): array
    {
        return ['id' => $commentId, 'posted_uid' => 'fake-user', 'content' => $content, 'posted_at' => now()->toIso8601String()];
    }

    public function updateTaskDates(string $accessToken, string $taskId, string $start, ?string $deadline): array
    {
        return ['id' => $taskId, 'due' => ['date' => $start], 'deadline_date' => $deadline];
    }

    public function updateTask(string $accessToken, string $taskId, array $attributes): array
    {
        return ['id' => $taskId] + $attributes;
    }

    public function setTaskCompletion(string $accessToken, string $taskId, bool $completed): void {}

    public function createTask(string $accessToken, array $attributes): array
    {
        return ['id' => 'fake-created-task'] + $attributes;
    }

    public function deleteTask(string $accessToken, string $taskId): void {}
}
