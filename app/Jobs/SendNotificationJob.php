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
            $class = Relation::getMorphedModel($this->notifiableType) ?? $this->notifiableType;

            if (! class_exists($class)) {
                Log::warning('SendNotificationJob: notifiable class missing', [
                    'type' => $this->notifiableType,
                ]);

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

        $callback();
    }
}
