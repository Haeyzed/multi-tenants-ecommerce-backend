<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use App\Models\Tenant\HR\Interview;
use App\Models\Tenant\HR\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * @var class-string<Interview>
     */
    protected $model = Interview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'interview_type' => InterviewType::Technical,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => InterviewStatus::Scheduled,
        ];
    }
}
