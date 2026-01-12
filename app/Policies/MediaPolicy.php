<?php

namespace App\Policies;

use App\Models\AppMedia;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine if the user can view any media.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'view media');
    }

    /**
     * Determine if the user can view the media.
     */
    public function view(User $user, AppMedia $media): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'view media');
    }

    /**
     * Determine if the user can upload media.
     */
    public function upload(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'upload media');
    }

    /**
     * Determine if the user can update the media.
     */
    public function update(User $user, AppMedia $media): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'update media');
    }

    /**
     * Determine if the user can delete the media.
     */
    public function delete(User $user, AppMedia $media): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'delete media');
    }

    /**
     * Determine if the user can bulk delete media.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'delete media');
    }

    /**
     * Determine if the user can download the media.
     */
    public function download(User $user, AppMedia $media): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $this->hasPermission($user, 'download media');
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
