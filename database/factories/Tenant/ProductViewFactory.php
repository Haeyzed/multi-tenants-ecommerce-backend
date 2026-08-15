<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductView>
 */
class ProductViewFactory extends Factory
{
    /**
     * @var class-string<ProductView>
     */
    protected $model = ProductView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'session_key' => null,
            'viewed_at' => now(),
        ];
    }

    /**
     * Indicate the view came from an anonymous storefront session.
     */
    public function anonymous(string $sessionKey): static
    {
        return $this->state(fn (): array => [
            'customer_id' => null,
            'session_key' => $sessionKey,
        ]);
    }
}
