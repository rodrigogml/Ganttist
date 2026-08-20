<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TodoistGateway;
use InvalidArgumentException;

final readonly class DependencyScopeValidator
{
    public function __construct(private TodoistGateway $gateway, private TodoistAccessTokenService $tokens) {}

    /** @param object{id: string, todoist_project_id: string} $project @param object{access_token_encrypted: string} $integration */
    public function validate(object $project, object $integration, string $predecessorId, string $successorId): void
    {
        $snapshot = $this->gateway->projectSnapshot($this->tokens->accessToken($integration), $project->todoist_project_id);
        $tasks = $snapshot['tasks']['results'] ?? $snapshot['tasks'] ?? [];
        $known = [];
        $groups = [];

        foreach ($tasks as $task) {
            $id = (string) ($task['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $known[$id] = true;
            $parentId = (string) ($task['parent_id'] ?? '');
            if ($parentId !== '') {
                $groups[$parentId] = true;
            }
        }

        if (! isset($known[$predecessorId], $known[$successorId])) {
            throw new InvalidArgumentException('As tarefas da dependência devem pertencer ao projeto Todoist selecionado.');
        }
        if (isset($groups[$successorId])) {
            throw new InvalidArgumentException('Um grupo pode ser usado somente como predecessor de uma tarefa comum.');
        }
        if (isset($groups[$predecessorId], $groups[$successorId])) {
            throw new InvalidArgumentException('Relações entre grupos não são permitidas.');
        }
    }
}
