<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Support\RbacPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds tenant Spatie roles.
 */
class RoleSeeder extends Seeder
{
    /**
     * Guard name for tenant roles.
     */
    private const string GUARD = 'tenant';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', self::GUARD);
        $admin->syncPermissions(RbacPermissions::NAMES);

        $manager = Role::findOrCreate('manager', self::GUARD);
        $manager->syncPermissions([
            'users.view',
            'users.create',
            'users.show',
            'users.update',
            'roles.view',
            'roles.show',
            'permissions.view',
            'permissions.show',
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
            'units.view',
            'units.create',
            'units.show',
            'units.update',
            'units.delete',
            'warehouses.view',
            'warehouses.create',
            'warehouses.show',
            'warehouses.update',
            'warehouses.delete',
            'products.view',
            'products.create',
            'products.show',
            'products.update',
            'products.delete',
            'variants.view',
            'variants.create',
            'variants.show',
            'variants.update',
            'variants.delete',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'customers.view',
            'customers.show',
            'customers.update',
            'collections.view',
            'collections.create',
            'collections.show',
            'collections.update',
            'tags.view',
            'tags.create',
            'tags.show',
            'tags.update',
            'badges.view',
            'badges.create',
            'badges.show',
            'badges.update',
            'reviews.view',
            'reviews.moderate',
            'options.view',
            'options.create',
            'options.show',
            'options.update',
            'attributes.view',
            'attributes.create',
            'attributes.show',
            'attributes.update',
        ]);

        Role::findOrCreate('customer', self::GUARD);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
