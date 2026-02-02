<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine if the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'view projects');
    }

    /**
     * Determine if the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'view projects');
    }

    /**
     * Determine if the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'create projects');
    }

    /**
     * Determine if the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'update projects');
    }

    /**
     * Determine if the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'delete projects');
    }

    /**
     * Determine if the user can bulk delete projects.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']) || $this->hasPermission($user, 'delete projects');
    }

    /**
     * Check if user has permission (safe check that doesn't throw exceptions).
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Exception $e) {
            return false;
        }
    }
}
