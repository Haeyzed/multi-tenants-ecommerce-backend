<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * @var class-string<Inventory>
     */
    protected $model = Inventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 500);

        return [
            'warehouse_id' => Warehouse::factory(),
            'warehouse_location_id' => null,
            'inventoryable_type' => Product::class,
            'inventoryable_id' => Product::factory(),
            'quantity' => $quantity,
            'reserved_quantity' => fake()->numberBetween(0, min($quantity, 50)),
            'reorder_level' => fake()->optional()->numberBetween(5, 50),
            'reorder_quantity' => fake()->optional()->numberBetween(10, 100),
        ];
    }
}
