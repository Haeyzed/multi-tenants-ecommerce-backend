<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Models\Tenant\Candidate;
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
        $candidate = Candidate::factory();

        return [
            'job_opening_id' => JobOpening::factory(),
            'candidate_id' => $candidate,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('080########'),
            'source' => ApplicationSource::Website,
            'applied_at' => now(),
            'status' => JobApplicationStatus::Received,
            'cover_letter' => fake()->optional()->paragraph(),
        ];
    }
}
