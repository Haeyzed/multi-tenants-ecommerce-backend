<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\FlashSale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    /**
     * @var class-string<FlashSale>
     */
    protected $model = FlashSale::class;

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
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'stack_with_coupons' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function stackWithCoupons(): static
    {
        return $this->state(fn (): array => [
            'stack_with_coupons' => true,
        ]);
    }
}
