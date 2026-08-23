<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Cms\CmsContentStatus;
use App\Models\Tenant\Content\Page;
use App\Models\Tenant\User;

/**
 * Authorization for tenant CMS pages.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('cms.view') || $user->can('cms.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Page $page): bool
    {
        if (! $user->can('cms.manage')) {
            return false;
        }

        $status = request()->input('status');

        if ($status === CmsContentStatus::Published->value || $status === CmsContentStatus::Published) {
            return $user->can('cms.publish');
        }

        return true;
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('cms.manage');
    }
}
