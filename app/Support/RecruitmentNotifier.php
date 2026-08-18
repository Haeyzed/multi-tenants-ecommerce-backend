<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Notification\NotificationChannel;
use App\Models\Tenant\Candidate;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;

/**
 * Fan-out recruitment notifications to HR staff and candidates.
 */
class RecruitmentNotifier
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly HrSettingsService $hrSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyStaff(string $key, array $payload): void
    {
        if (! $this->hrSettings->notifyRecruitment()) {
            return;
        }

        $staff = User::query()
            ->permission(['hr.recruitment.manage', 'hr.recruitment.hire', 'hr.recruitment.view'])
            ->get();

        foreach ($staff as $user) {
            $this->notifications->send(
                $user,
                $key,
                [
                    'user_name' => NotifiableDisplayName::resolve($user),
                    ...$payload,
                ],
                [
                    NotificationChannel::Email->value,
                    NotificationChannel::Database->value,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyCandidate(Candidate $candidate, string $key, array $payload): void
    {
        if (! $this->hrSettings->notifyRecruitment()) {
            return;
        }

        $this->notifications->send(
            $candidate,
            $key,
            [
                'user_name' => NotifiableDisplayName::resolve($candidate),
                'email' => $candidate->email,
                ...$payload,
            ],
            [
                NotificationChannel::Email->value,
            ],
        );
    }
}
