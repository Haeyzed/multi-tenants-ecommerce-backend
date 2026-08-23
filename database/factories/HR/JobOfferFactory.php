<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\JobOfferStatus;
use App\Models\Tenant\HR\JobApplication;
use App\Models\Tenant\HR\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOffer>
 */
class JobOfferFactory extends Factory
{
    /**
     * @var class-string<JobOffer>
     */
    protected $model = JobOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'position' => fake()->jobTitle(),
            'salary' => fake()->randomFloat(2, 150000, 800000),
            'currency' => 'NGN',
            'start_date' => now()->addWeeks(2)->toDateString(),
            'expires_at' => now()->addWeeks(1)->toDateString(),
            'status' => JobOfferStatus::Draft,
        ];
    }
}
