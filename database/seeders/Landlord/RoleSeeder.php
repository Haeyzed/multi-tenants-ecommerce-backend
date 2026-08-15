<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Support\RbacPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds landlord Spatie roles.
 */
class RoleSeeder extends Seeder
{
    /**
     * Guard name for landlord roles.
     */
    private const string GUARD = 'landlord';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super-admin', self::GUARD);

        $admin = Role::findOrCreate('admin', self::GUARD);
        $admin->syncPermissions(RbacPermissions::NAMES);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
