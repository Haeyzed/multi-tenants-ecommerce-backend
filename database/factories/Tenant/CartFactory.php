<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\CartStatus;
use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * @var class-string<Cart>
     */
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'currency' => 'USD',
            'status' => CartStatus::Active,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }

    /**
     * Indicate the cart was converted to an order.
     */
    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Converted,
        ]);
    }
}
