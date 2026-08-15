<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use Illuminate\Database\Seeder;

/**
 * Optional demo catalog data for tenant environments.
 *
 * Not called from TenantDatabaseSeeder by default — invoke explicitly for demos.
 */
class CatalogSeeder extends Seeder
{
    /**
     * Seed sample brands and a nested category tree.
     */
    public function run(): void
    {
        foreach (['Samsung', 'Apple', 'Sony', 'LG', 'Nike', 'Adidas'] as $index => $name) {
            Brand::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => $name.' brand',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $electronics = Category::query()->firstOrCreate(
            ['name' => 'Electronics', 'parent_id' => null],
            ['description' => 'Electronic products', 'is_active' => true, 'sort_order' => 1],
        );

        foreach (['Phones', 'Laptops', 'TVs'] as $index => $name) {
            Category::query()->firstOrCreate(
                ['name' => $name, 'parent_id' => $electronics->id],
                ['is_active' => true, 'sort_order' => $index + 1],
            );
        }

        $fashion = Category::query()->firstOrCreate(
            ['name' => 'Fashion', 'parent_id' => null],
            ['description' => 'Fashion products', 'is_active' => true, 'sort_order' => 2],
        );

        foreach (['Men', 'Women', 'Shoes'] as $index => $name) {
            Category::query()->firstOrCreate(
                ['name' => $name, 'parent_id' => $fashion->id],
                ['is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
