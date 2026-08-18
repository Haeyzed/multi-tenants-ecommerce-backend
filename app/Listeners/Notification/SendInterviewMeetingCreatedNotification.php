<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewMeetingCreated;
use App\Support\RecruitmentNotifier;

class SendInterviewMeetingCreatedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewMeetingCreated $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening', 'currentMeeting']);
        $this->notifier->notifyStaff(
            'hr.interview.meeting.created',
            $interview->recruitmentNotificationPayload(includeHostUrl: true),
        );
    }
}
