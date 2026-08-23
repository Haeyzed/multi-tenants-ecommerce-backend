<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Notification\NotificationChannel;
use App\Models\Tenant\HR\Candidate;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;

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

        foreach ($this->staffWithRecruitmentAccess() as $user) {
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

    /**
     * @return Collection<int, User>
     */
    protected function staffWithRecruitmentAccess(): Collection
    {
        $names = [];

        foreach (['hr.recruitment.manage', 'hr.recruitment.hire', 'hr.recruitment.view'] as $name) {
            try {
                Permission::findByName($name, 'tenant');
                $names[] = $name;
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        if ($names === []) {
            return collect();
        }

        return User::query()->permission($names)->get();
    }
}
