<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class ProjectPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage projects');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }
}
