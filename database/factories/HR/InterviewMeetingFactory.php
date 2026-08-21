<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\InterviewMeetingStatus;
use App\Enums\Tenant\HR\MeetingProvider;
use App\Models\HR\Interview;
use App\Models\HR\InterviewMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewMeeting>
 */
class InterviewMeetingFactory extends Factory
{
    /**
     * @var class-string<InterviewMeeting>
     */
    protected $model = InterviewMeeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay();

        return [
            'interview_id' => Interview::factory(),
            'provider' => MeetingProvider::Manual,
            'external_id' => null,
            'join_url' => fake()->url(),
            'host_url' => null,
            'password' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => InterviewMeetingStatus::Created,
            'is_current' => true,
        ];
    }
}
