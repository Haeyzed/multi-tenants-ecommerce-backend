<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Landlord\User;
use Database\Seeders\Landlord\FeatureSeeder;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\PlanSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the central (landlord) database.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            FeatureSeeder::class,
            PlanSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => 'password',
            ],
        );

        $user->assignRole('super-admin');
    }
}
