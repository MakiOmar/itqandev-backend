<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class BlogPostPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'editor'])
            || $this->hasPermission($user, 'manage blog');
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $this->create($user);
    }
}
