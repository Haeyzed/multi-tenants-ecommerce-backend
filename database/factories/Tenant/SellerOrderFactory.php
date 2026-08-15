<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerOrder>
 */
class SellerOrderFactory extends Factory
{
    /**
     * @var class-string<SellerOrder>
     */
    protected $model = SellerOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'seller_id' => Seller::factory()->sellable(),
            'status' => SellerOrderStatus::Pending,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'commission_total' => '0.00',
            'seller_total' => '100.00',
        ];
    }
}
