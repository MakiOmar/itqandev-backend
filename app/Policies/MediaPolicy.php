<?php

namespace App\Policies;

use App\Models\AppMedia;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class MediaPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage media');
    }

    public function view(User $user, AppMedia $media): bool
    {
        return $this->viewAny($user);
    }

    public function upload(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AppMedia $media): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AppMedia $media): bool
    {
        return $this->viewAny($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function download(User $user, AppMedia $media): bool
    {
        return $this->viewAny($user);
    }
}
