<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\JobOfferAccepted;
use App\Support\RecruitmentNotifier;

class SendJobOfferAcceptedNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(JobOfferAccepted $event): void
    {
        $offer = $event->offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;
        $payload = [
            'job_title' => $offer->position ?? $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
        ];

        $this->notifier->notifyStaff('hr.offer.accepted', $payload);

        if ($application?->candidate !== null) {
            $this->notifier->notifyCandidate($application->candidate, 'hr.offer.accepted', $payload);
        }
    }
}
