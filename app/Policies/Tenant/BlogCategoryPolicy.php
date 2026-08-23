<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Content\BlogCategory;
use App\Models\Tenant\User;

/**
 * Authorization for tenant blog categories.
 */
class BlogCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function view(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, BlogCategory $blogCategory): bool
    {
        return $user->can('cms.manage');
    }
}
