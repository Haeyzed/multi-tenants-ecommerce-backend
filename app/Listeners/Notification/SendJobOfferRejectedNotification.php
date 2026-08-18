<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\JobOfferRejected;
use App\Support\RecruitmentNotifier;

class SendJobOfferRejectedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(JobOfferRejected $event): void
    {
        $offer = $event->offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;
        $payload = [
            'job_title' => $offer->position ?? $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
        ];

        $this->notifier->notifyStaff('hr.offer.rejected', $payload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.offer.rejected', $payload);
        }
    }
}
