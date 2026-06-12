<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class RoleAssignmentGuard
{
    /**
     * @param  list<int>  $roleIds
     * @return list<int>
     */
    public static function assertAssignable(User $actor, array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return [];
        }

        $roles = Role::query()->whereIn('id', $roleIds)->get(['id', 'name']);
        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => [__('One or more roles are invalid.')],
            ]);
        }

        if ($actor->hasRole('super_admin')) {
            return $roleIds;
        }

        $forbidden = $roles->filter(fn (Role $role) => in_array($role->name, ['super_admin'], true));
        if ($forbidden->isNotEmpty()) {
            throw ValidationException::withMessages([
                'role_ids' => [__('You cannot assign elevated roles.')],
            ]);
        }

        if (! $actor->hasAnyRole(['super_admin', 'admin']) && ! $actor->can('manage roles')) {
            throw ValidationException::withMessages([
                'role_ids' => [__('You are not allowed to assign roles.')],
            ]);
        }

        return $roleIds;
    }
}
