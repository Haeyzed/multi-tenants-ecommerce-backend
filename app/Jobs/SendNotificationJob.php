<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Landlord\Tenant;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queued delivery of a templated notification.
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $onlyChannels
     */
    public function __construct(
        public readonly string $notifiableType,
        public readonly int|string $notifiableId,
        public readonly string $notificationKey,
        public readonly array $data = [],
        public readonly ?array $onlyChannels = null,
        public readonly int|string|null $tenantId = null,
    ) {
        $this->afterCommit();
    }

    public function handle(NotificationService $notifications): void
    {
        $callback = function () use ($notifications): void {
            $class = $this->resolveNotifiableClass();

            if ($class === null) {
                return;
            }

            $notifiable = $class::query()->find($this->notifiableId);

            if ($notifiable === null) {
                Log::warning('SendNotificationJob: notifiable not found', [
                    'type' => $this->notifiableType,
                    'id' => $this->notifiableId,
                ]);

                return;
            }

            $notifications->sendNow(
                $notifiable,
                $this->notificationKey,
                $this->data,
                $this->onlyChannels,
            );
        };

        if ($this->tenantId !== null) {
            $tenant = Tenant::query()->find($this->tenantId);

            if ($tenant === null) {
                Log::warning('SendNotificationJob: tenant not found', ['tenant_id' => $this->tenantId]);

                return;
            }

            $tenant->run($callback);

            return;
        }

        $class = $this->resolveNotifiableClass();

        // Tenant models must not be resolved on the central connection.
        if ($this->isTenantNotifiable($class)) {
            Log::warning('SendNotificationJob: tenant id is required for tenant notifiable', [
                'notification_key' => $this->notificationKey,
                'notifiable_type' => $this->notifiableType,
                'notifiable_id' => $this->notifiableId,
            ]);

            return;
        }

        // Landlord / central delivery — leave any leftover tenant context.
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        $callback();
    }

    /**
     * @return class-string|null
     */
    private function resolveNotifiableClass(): ?string
    {
        $class = Relation::getMorphedModel($this->notifiableType) ?? $this->notifiableType;

        if (! is_string($class) || ! class_exists($class)) {
            Log::warning('SendNotificationJob: notifiable class missing', [
                'type' => $this->notifiableType,
            ]);

            return null;
        }

        return $class;
    }

    /**
     * @param  class-string|null  $class
     */
    private function isTenantNotifiable(?string $class): bool
    {
        return is_string($class) && str_starts_with($class, 'App\\Models\\Tenant\\');
    }
}
