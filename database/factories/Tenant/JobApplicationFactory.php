<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @var class-string<JobApplication>
     */
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_opening_id' => JobOpening::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('080########'),
            'status' => JobApplicationStatus::Received,
            'cover_letter' => fake()->optional()->paragraph(),
        ];
    }
}
