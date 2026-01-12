<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine if the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'view categories');
    }

    /**
     * Determine if the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'view categories');
    }

    /**
     * Determine if the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'create categories');
    }

    /**
     * Determine if the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'update categories');
    }

    /**
     * Determine if the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'delete categories');
    }

    /**
     * Determine if the user can bulk delete categories.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'delete categories');
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
