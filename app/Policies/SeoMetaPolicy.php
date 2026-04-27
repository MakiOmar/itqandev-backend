<?php

namespace App\Policies;

use App\Models\SeoMeta;
use App\Models\User;
use App\Policies\Concerns\ChecksManagePermissions;

class SeoMetaPolicy
{
    use ChecksManagePermissions;

    /**
     * Create or update SEO metadata on projects, categories, blog posts, etc.
     */
    public function update(User $user, ?SeoMeta $seoMeta = null): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'company', 'editor'])
            || $this->hasPermission($user, 'manage seo');
    }
}
