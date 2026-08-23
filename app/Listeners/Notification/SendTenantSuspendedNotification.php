<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\TenantSuspended;
use App\Models\Landlord\User;
use App\Services\Notification\NotificationService;

class SendTenantSuspendedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TenantSuspended $event): void
    {
        $tenant = $event->tenant;

        foreach (User::query()->permission('tenants.view')->get() as $admin) {
            $this->notifications->send($admin, 'tenant.suspended', [
                'tenant_name' => $tenant->name,
                'tenant_id' => (string) $tenant->getTenantKey(),
            ]);
        }
    }
}
