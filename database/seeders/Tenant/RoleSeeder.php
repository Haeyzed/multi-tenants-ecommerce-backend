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
        ]);

        Role::findOrCreate('customer', self::GUARD);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
