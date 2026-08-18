<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\CandidateHired;
use App\Support\RecruitmentNotifier;

class SendCandidateHiredNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(CandidateHired $event): void
    {
        $application = $event->application->loadMissing(['candidate', 'jobOpening']);
        $payload = [
            'job_title' => $application->jobOpening?->title ?? '',
            'candidate_name' => trim($application->first_name.' '.$application->last_name),
            'employee_number' => $event->employee->employee_number ?? '',
        ];

        $this->notifier->notifyStaff('hr.candidate.hired', $payload);
    }
}
