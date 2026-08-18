<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewMeetingFailed;
use App\Support\RecruitmentNotifier;

class SendInterviewMeetingFailedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewMeetingFailed $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening']);
        $payload = $interview->recruitmentNotificationPayload(includeHostUrl: false);
        $payload['meeting_provider'] = $event->provider;

        $this->notifier->notifyStaff('hr.interview.meeting.failed', $payload);
    }
}
