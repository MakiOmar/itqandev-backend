<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class MenuPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage menus');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->create($user);
    }
}
