<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class FormPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage forms');
    }

    public function view(User $user, Form $form): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Form $form): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Form $form): bool
    {
        return $this->viewAny($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }
}
