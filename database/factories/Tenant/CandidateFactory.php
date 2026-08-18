<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\CandidateStatus;
use App\Models\Tenant\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * @var class-string<Candidate>
     */
    protected $model = Candidate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('080########'),
            'status' => CandidateStatus::Active,
        ];
    }
}
