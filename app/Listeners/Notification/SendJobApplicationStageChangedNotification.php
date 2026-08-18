<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Events\JobApplicationStageChanged;
use App\Support\RecruitmentNotifier;

class SendJobApplicationStageChangedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(JobApplicationStageChanged $event): void
    {
        $key = match ($event->toStatus) {
            JobApplicationStatus::Shortlisted => 'hr.application.shortlisted',
            JobApplicationStatus::Rejected => 'hr.application.rejected',
            default => null,
        };

        if ($key === null) {
            return;
        }

        $application = $event->application->loadMissing(['candidate', 'jobOpening']);
        $payload = [
            'job_title' => $application->jobOpening?->title ?? '',
            'candidate_name' => trim($application->first_name.' '.$application->last_name),
        ];

        $this->notifier->notifyStaff($key, $payload);

        if ($application->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, $key, $payload);
        }
    }
}
