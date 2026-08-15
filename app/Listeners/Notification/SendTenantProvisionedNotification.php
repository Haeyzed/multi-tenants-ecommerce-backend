<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\TenantProvisioned;
use App\Models\Landlord\User;
use App\Services\Notification\NotificationService;

class SendTenantProvisionedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TenantProvisioned $event): void
    {
        $tenant = $event->tenant;

        $admins = User::query()
            ->permission('tenants.view')
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->send($admin, 'tenant.created', [
                'tenant_name' => $tenant->name,
                'tenant_id' => (string) $tenant->getTenantKey(),
                'tenant_email' => $tenant->email ?? '',
            ]);
        }
    }
}
