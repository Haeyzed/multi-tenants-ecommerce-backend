<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Content\ContentStatus;
use App\Models\Tenant\Content\BlogPost;
use App\Models\Tenant\User;

/**
 * Authorization for tenant blog posts.
 */
class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        if (! $user->can('cms.manage')) {
            return false;
        }

        $status = request()->input('status');

        if ($status === ContentStatus::Published->value || $status === ContentStatus::Published) {
            return $user->can('cms.publish');
        }

        return true;
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $user->can('cms.manage');
    }
}
