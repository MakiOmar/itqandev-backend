<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class UserPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $auth): bool
    {
        return $auth->hasRole(['super_admin', 'admin'])
            || $this->hasPermission($auth, 'manage users');
    }

    public function view(User $auth, User $model): bool
    {
        if ($auth->id === $model->id) {
            return true;
        }

        return $this->viewAny($auth);
    }

    public function create(User $auth): bool
    {
        return $auth->hasRole(['super_admin', 'admin'])
            || $this->hasPermission($auth, 'manage users');
    }

    public function update(User $auth, User $model): bool
    {
        if ($auth->id === $model->id) {
            return true;
        }

        return $this->create($auth);
    }

    /**
     * Assign or sync roles on a user (including self) — requires manage roles.
     */
    public function assignRoles(User $auth, User $model): bool
    {
        return $auth->hasRole(['super_admin', 'admin'])
            || $this->hasPermission($auth, 'manage roles');
    }

    public function delete(User $auth, User $model): bool
    {
        return $this->create($auth);
    }
}
