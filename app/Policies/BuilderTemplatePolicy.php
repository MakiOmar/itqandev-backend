<?php

namespace App\Policies;

use App\Models\BuilderTemplate;
use App\Models\User;

/** Builder templates are part of Appearance, which only admins manage (same as `manageSettings`). */
class BuilderTemplatePolicy
{
    private const MANAGE_ROLES = ['super_admin', 'admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function view(User $user, BuilderTemplate $template): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, BuilderTemplate $template): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, BuilderTemplate $template): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function bulkDelete(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }
}
