<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @var class-string<Order>
     */
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);
        $discountTotal = fake()->randomFloat(2, 0, min(20, $subtotal));
        $taxTotal = fake()->randomFloat(2, 0, 50);
        $shippingTotal = fake()->randomFloat(2, 0, 30);
        $grandTotal = round($subtotal - $discountTotal + $taxTotal + $shippingTotal, 2);

        return [
            'order_number' => fake()->unique()->bothify('ORD-########'),
            'customer_id' => Customer::factory(),
            'currency' => 'USD',
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $grandTotal,
            'shipping_method_id' => null,
            'shipping_address_snapshot' => null,
            'billing_address_snapshot' => null,
            'notes' => fake()->optional()->sentence(),
            'idempotency_key' => fake()->optional()->uuid(),
            'placed_at' => now(),
            'confirmed_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate the order is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
