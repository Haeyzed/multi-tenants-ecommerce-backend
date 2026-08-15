<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Models\Landlord\Feature;
use Illuminate\Database\Seeder;

/**
 * Seeds stable platform feature definitions.
 */
class FeatureSeeder extends Seeder
{
    /**
     * Feature definitions keyed by stable slug.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const array FEATURES = [
        'products' => [
            'name' => 'Products',
            'description' => 'Manage store products and catalog.',
        ],
        'orders' => [
            'name' => 'Orders',
            'description' => 'Create and manage customer orders.',
        ],
        'customers' => [
            'name' => 'Customers',
            'description' => 'Manage customer accounts.',
        ],
        'inventory' => [
            'name' => 'Inventory',
            'description' => 'Track stock and inventory levels.',
        ],
        'advanced-reports' => [
            'name' => 'Advanced Reports',
            'description' => 'Access advanced reporting dashboards.',
        ],
        'custom-domain' => [
            'name' => 'Custom Domain',
            'description' => 'Use a custom storefront domain.',
        ],
        'api-access' => [
            'name' => 'API Access',
            'description' => 'Programmatic API access for integrations.',
        ],
        'gift-cards' => [
            'name' => 'Gift Cards',
            'description' => 'Issue and redeem prepaid gift cards.',
        ],
        'store-credit' => [
            'name' => 'Store Credit',
            'description' => 'Maintain customer store credit wallets.',
        ],
        'loyalty' => [
            'name' => 'Loyalty & Rewards',
            'description' => 'Award and redeem customer loyalty points.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::FEATURES as $slug => $feature) {
            Feature::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
