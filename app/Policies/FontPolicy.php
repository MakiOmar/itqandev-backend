<?php

namespace App\Policies;

use App\Models\Font;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class FontPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage fonts');
    }

    public function view(User $user, Font $font): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Font $font): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Font $font): bool
    {
        return $this->create($user);
    }
}
