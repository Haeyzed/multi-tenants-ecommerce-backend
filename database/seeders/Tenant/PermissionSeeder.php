<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Support\RbacPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds tenant Spatie permissions.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Guard name for tenant permissions.
     */
    private const string GUARD = 'tenant';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RbacPermissions::NAMES as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
