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
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening', 'currentMeeting']);
        $application = $interview->application;
        $candidatePayload = $interview->recruitmentNotificationPayload(includeHostUrl: false);
        $staffPayload = $interview->recruitmentNotificationPayload(includeHostUrl: true);

        $this->notifier->notifyStaff('hr.interview.scheduled', $staffPayload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.interview.scheduled', $candidatePayload);
        }
    }
}
