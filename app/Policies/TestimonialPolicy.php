<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class TestimonialPolicy
{
    use ChecksManagePermissions;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage testimonials');
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->create($user);
    }

    public function bulkDelete(User $user): bool
    {
        return $this->viewAny($user);
    }
}
