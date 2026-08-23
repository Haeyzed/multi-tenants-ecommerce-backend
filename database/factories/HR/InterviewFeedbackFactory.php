<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Models\Tenant\HR\Interview;
use App\Models\Tenant\HR\InterviewFeedback;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewFeedback>
 */
class InterviewFeedbackFactory extends Factory
{
    /**
     * @var class-string<InterviewFeedback>
     */
    protected $model = InterviewFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_id' => Interview::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'recommendation' => InterviewRecommendation::Hire,
            'comments' => fake()->optional()->sentence(),
        ];
    }
}
