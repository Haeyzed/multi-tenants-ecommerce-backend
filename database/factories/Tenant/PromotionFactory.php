<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\PromotionType;
use App\Models\Tenant\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @var class-string<Promotion>
     */
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'type' => PromotionType::PercentageOffOrder,
            'value' => '10.00',
            'min_order_amount' => '0.00',
            'max_discount' => null,
            'priority' => 0,
            'is_exclusive' => false,
            'is_stackable' => true,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'metadata' => null,
        ];
    }

    public function exclusive(int $priority = 10): static
    {
        return $this->state(fn (): array => [
            'is_exclusive' => true,
            'is_stackable' => false,
            'priority' => $priority,
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::FreeShipping,
            'value' => '0.00',
        ]);
    }
}
