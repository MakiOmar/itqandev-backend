<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class ServicePolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage services');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }
}
