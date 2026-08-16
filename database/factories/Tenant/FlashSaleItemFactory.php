<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\FlashSale;
use App\Models\Tenant\FlashSaleItem;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSaleItem>
 */
class FlashSaleItemFactory extends Factory
{
    /**
     * @var class-string<FlashSaleItem>
     */
    protected $model = FlashSaleItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flash_sale_id' => FlashSale::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'sale_price' => '50.00',
            'qty_limit' => null,
            'sold_qty' => 0,
            'per_customer_limit' => null,
            'customer_group_id' => null,
        ];
    }

    public function withQtyLimit(int $limit): static
    {
        return $this->state(fn (): array => [
            'qty_limit' => $limit,
        ]);
    }

    public function withPerCustomerLimit(int $limit): static
    {
        return $this->state(fn (): array => [
            'per_customer_limit' => $limit,
        ]);
    }
}
