<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\JobApplicationReceived;
use App\Support\RecruitmentNotifier;

class SendJobApplicationReceivedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(JobApplicationReceived $event): void
    {
        $application = $event->application->loadMissing(['candidate', 'jobOpening']);
        $payload = [
            'job_title' => $application->jobOpening?->title ?? '',
            'candidate_name' => trim($application->first_name.' '.$application->last_name),
        ];

        $this->notifier->notifyStaff('hr.application.received', $payload);

        if ($application->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.application.received', $payload);
        }
    }
}
