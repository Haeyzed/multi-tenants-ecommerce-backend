<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\PerformanceReviewStatus;
use App\Models\HR\Employee;
use App\Models\HR\PerformanceCycle;
use App\Models\HR\PerformanceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    /**
     * @var class-string<PerformanceReview>
     */
    protected $model = PerformanceReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_id' => PerformanceCycle::factory(),
            'employee_id' => Employee::factory(),
            'rating' => fake()->randomFloat(1, 1, 5),
            'summary' => fake()->optional()->sentence(),
            'status' => PerformanceReviewStatus::Draft,
        ];
    }
}
