<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\TenantActivated;
use App\Models\Landlord\User;
use App\Services\Notification\NotificationService;

class SendTenantActivatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TenantActivated $event): void
    {
        $tenant = $event->tenant;

        foreach (User::query()->permission('tenants.view')->get() as $admin) {
            $this->notifications->send($admin, 'tenant.activated', [
                'tenant_name' => $tenant->name,
                'tenant_id' => (string) $tenant->getTenantKey(),
            ]);
        }
    }
}
