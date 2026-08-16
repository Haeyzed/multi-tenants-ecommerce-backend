<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosTerminal>
 */
class PosTerminalFactory extends Factory
{
    /**
     * @var class-string<PosTerminal>
     */
    protected $model = PosTerminal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Terminal',
            'code' => strtoupper(fake()->unique()->bothify('POS-###??')),
            'status' => PosTerminalStatus::Active,
            'warehouse_id' => null,
            'location_label' => fake()->optional()->streetAddress(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PosTerminalStatus::Inactive,
        ]);
    }

    public function forWarehouse(?Warehouse $warehouse = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'warehouse_id' => $warehouse?->id ?? Warehouse::factory(),
        ]);
    }
}
