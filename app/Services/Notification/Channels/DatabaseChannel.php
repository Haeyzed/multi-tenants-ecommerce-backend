<?php

declare(strict_types=1);

namespace App\Services\Notification\Channels;

use App\Contracts\Notification\NotificationChannel;
use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\DeliveryStatus;
use App\Enums\Notification\NotificationChannel as NotificationChannelEnum;
use App\Models\Notification\NotificationDelivery;
use App\Notifications\TemplatedNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Persists an in-app notification via Laravel's database channel.
 */
class DatabaseChannel implements NotificationChannel
{
    public function name(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Database;
    }

    public function send(Model $notifiable, NotificationPayload $payload): NotificationDelivery
    {
        /** @var Model&object{notifyNow: callable} $notifiable */
        $notifiable->notifyNow(new TemplatedNotification($payload, [NotificationChannelEnum::Database->value]));

        return NotificationDelivery::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_key' => $payload->key,
            'channel' => NotificationChannelEnum::Database,
            'status' => DeliveryStatus::Sent,
            'provider' => 'database',
            'sent_at' => now(),
        ]);
    }
}
