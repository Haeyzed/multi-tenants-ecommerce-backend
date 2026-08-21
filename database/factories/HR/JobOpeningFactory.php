<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\HR\JobOpening;
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
            'status' => JobOpeningStatus::Published,
            'openings_count' => 1,
            'description' => fake()->optional()->sentence(),
            'closes_at' => now()->addMonth()->toDateString(),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => JobOpeningStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => JobOpeningStatus::Paused,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => JobOpeningStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
