<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\Tenant\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * @var class-string<JobOpening>
     */
    protected $model = JobOpening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'code' => fake()->unique()->bothify('JOB-###'),
            'status' => JobOpeningStatus::Open,
            'openings_count' => 1,
            'description' => fake()->optional()->sentence(),
            'closes_at' => now()->addMonth()->toDateString(),
        ];
    }
}
