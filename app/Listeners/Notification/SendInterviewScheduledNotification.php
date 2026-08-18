<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewScheduled;
use App\Support\RecruitmentNotifier;

class SendInterviewScheduledNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewScheduled $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $interview->application;
        $payload = [
            'job_title' => $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
            'scheduled_at' => $interview->scheduled_at->toDateTimeString(),
        ];

        $this->notifier->notifyStaff('hr.interview.scheduled', $payload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.interview.scheduled', $payload);
        }
    }
}
