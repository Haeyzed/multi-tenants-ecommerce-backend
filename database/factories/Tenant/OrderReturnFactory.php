<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\ReturnReason;
use App\Enums\Tenant\Commerce\ReturnStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_number' => 'RET-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'status' => ReturnStatus::Requested,
            'reason' => ReturnReason::ChangedMind,
            'customer_note' => fake()->optional()->sentence(),
            'requested_at' => now(),
        ];
    }
}
