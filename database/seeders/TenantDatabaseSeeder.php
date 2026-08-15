<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeds the tenant database with RBAC defaults.
 */
class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }
}
