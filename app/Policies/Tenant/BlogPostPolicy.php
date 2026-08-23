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
        return $user->can('content.view') || $user->can('content.manage');
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $user->can('content.view') || $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        if (! $user->can('content.manage')) {
            return false;
        }

        $status = request()->input('status');

        if ($status === ContentStatus::Published->value || $status === ContentStatus::Published) {
            return $user->can('content.publish');
        }

        return true;
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $user->can('content.manage');
    }
}
