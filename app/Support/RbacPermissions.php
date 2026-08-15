<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical Spatie permission names shared by landlord and tenant RBAC.
 */
final class RbacPermissions
{
    /**
     * All application permission names.
     *
     * @var list<string>
     */
    public const NAMES = [
        'users.view',
        'users.create',
        'users.show',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.show',
        'roles.update',
        'roles.delete',
        'permissions.view',
        'permissions.create',
        'permissions.show',
        'permissions.update',
        'permissions.delete',
        'tenants.view',
        'tenants.create',
        'tenants.show',
        'tenants.update',
        'tenants.delete',
        'domains.view',
        'domains.create',
        'domains.show',
        'domains.update',
        'domains.delete',
        'tenant-profiles.view',
        'tenant-profiles.create',
        'tenant-profiles.show',
        'tenant-profiles.update',
        'tenant-profiles.delete',
        'plans.view',
        'plans.create',
        'plans.show',
        'plans.update',
        'plans.delete',
        'features.view',
        'features.create',
        'features.show',
        'features.update',
        'features.delete',
        'subscriptions.view',
        'subscriptions.create',
        'subscriptions.show',
        'subscriptions.update',
        'subscriptions.delete',
        'notifications.view',
        'notification-templates.view',
        'notification-templates.create',
        'notification-templates.show',
        'notification-templates.update',
        'notification-templates.delete',
        'brands.view',
        'brands.create',
        'brands.show',
        'brands.update',
        'brands.delete',
        'categories.view',
        'categories.create',
        'categories.show',
        'categories.update',
        'categories.delete',
    ];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}
}
