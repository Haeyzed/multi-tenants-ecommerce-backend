<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewCancelled;
use App\Support\RecruitmentNotifier;

class SendInterviewCancelledNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewCancelled $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening', 'currentMeeting']);
        $application = $interview->application;
        $payload = $interview->recruitmentNotificationPayload(includeHostUrl: false);

        $this->notifier->notifyStaff('hr.interview.cancelled', $payload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.interview.cancelled', $payload);
        }
    }
}
