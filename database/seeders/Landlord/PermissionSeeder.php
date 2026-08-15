<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Support\RbacPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds landlord Spatie permissions.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Guard name for landlord permissions.
     */
    private const string GUARD = 'landlord';

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
