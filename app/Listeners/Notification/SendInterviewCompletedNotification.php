<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\InterviewCompleted;
use App\Support\RecruitmentNotifier;

class SendInterviewCompletedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(InterviewCompleted $event): void
    {
        $interview = $event->interview->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $interview->application;
        $payload = [
            'job_title' => $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
        ];

        $this->notifier->notifyStaff('hr.interview.completed', $payload);
    }
}
