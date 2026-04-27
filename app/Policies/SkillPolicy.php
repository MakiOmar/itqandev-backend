<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class SkillPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage skills');
    }

    public function view(User $user, Skill $skill): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Skill $skill): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Skill $skill): bool
    {
        return $this->viewAny($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }
}
