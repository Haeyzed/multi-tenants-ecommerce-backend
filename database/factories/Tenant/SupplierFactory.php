<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Procurement\SupplierStatus;
use App\Models\Tenant\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @var class-string<Supplier>
     */
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->bothify('SUP-###??'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website' => fake()->optional()->url(),
            'tax_number' => fake()->optional()->bothify('TAX-########'),
            'status' => SupplierStatus::Active,
            'address_line_1' => fake()->optional()->streetAddress(),
            'address_line_2' => null,
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'postal_code' => fake()->optional()->postcode(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate the supplier is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => SupplierStatus::Inactive,
        ]);
    }
}
