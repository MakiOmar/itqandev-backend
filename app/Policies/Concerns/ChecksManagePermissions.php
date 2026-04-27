<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksManagePermissions
{
    protected function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Exception) {
            return false;
        }
    }
}
