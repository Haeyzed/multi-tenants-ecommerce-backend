<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use App\Models\Tenant\HR\PerformanceCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    /**
     * @var class-string<PerformanceCycle>
     */
    protected $model = PerformanceCycle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->year().' H1 review',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->startOfYear()->addMonths(6)->toDateString(),
            'status' => PerformanceCycleStatus::Active,
        ];
    }
}
