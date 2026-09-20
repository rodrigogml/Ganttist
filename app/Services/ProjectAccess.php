<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProjectAccess
{
    public function member(User $user, string $projectId): object
    {
        return DB::table('project_members')
            ->where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->first() ?? abort(404);
    }

    public function view(User $user, string $projectId): object
    {
        return $this->member($user, $projectId);
    }

    public function edit(User $user, string $projectId): object
    {
        $member = $this->member($user, $projectId);
        abort_unless(in_array($member->role, ['owner', 'editor'], true), 403);

        return $member;
    }

    public function document(User $user, string $projectId, string $documentId, bool $edit = false): object
    {
        $edit ? $this->edit($user, $projectId) : $this->view($user, $projectId);

        return DB::table('project_documents')
            ->where('id', $documentId)
            ->where('project_id', $projectId)
            ->first() ?? abort(404);
    }
}
