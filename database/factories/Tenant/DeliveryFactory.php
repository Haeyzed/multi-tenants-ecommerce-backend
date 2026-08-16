<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @var class-string<Delivery>
     */
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'shipment_id' => null,
            'driver_id' => null,
            'status' => DeliveryStatus::Pending,
            'notes' => null,
        ];
    }

    /**
     * Mark the delivery as assigned to a driver.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => now(),
        ]);
    }
}
