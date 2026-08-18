<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewRescheduled;
use App\Support\RecruitmentNotifier;

class SendInterviewRescheduledNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewRescheduled $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening', 'currentMeeting']);
        $application = $interview->application;
        $candidatePayload = $interview->recruitmentNotificationPayload(includeHostUrl: false);
        $staffPayload = $interview->recruitmentNotificationPayload(includeHostUrl: true);

        $this->notifier->notifyStaff('hr.interview.rescheduled', $staffPayload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.interview.rescheduled', $candidatePayload);
        }
    }
}
