<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\JobOfferSent;
use App\Support\RecruitmentNotifier;

class SendJobOfferSentNotification
{
    public function __construct(private readonly RecruitmentNotifier $notifier) {}

    public function handle(JobOfferSent $event): void
    {
        $offer = $event->offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;
        $payload = [
            'job_title' => $offer->position ?? $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
        ];

        $this->notifier->notifyStaff('hr.offer.sent', $payload);

        if ($application?->candidate !== null) {
            $candidatePayload = $payload;

            if ($event->publicToken !== null) {
                $candidatePayload['offer_token'] = $event->publicToken;
            }

            $this->notifier->notifyCandidate($application->candidate, 'hr.offer.sent', $candidatePayload);
        }
    }
}
