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
use Throwable;

/**
 * Delivers email via Laravel's mail notification channel.
 */
class EmailChannel implements NotificationChannel
{
    public function name(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Email;
    }

    public function send(Model $notifiable, NotificationPayload $payload): NotificationDelivery
    {
        try {
            /** @var Model&object{notifyNow: callable} $notifiable */
            $notifiable->notifyNow(new TemplatedNotification($payload, [NotificationChannelEnum::Email->value]));

            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Email,
                'status' => DeliveryStatus::Sent,
                'provider' => 'mail',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Email,
                'status' => DeliveryStatus::Failed,
                'provider' => 'mail',
                'error' => $exception->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }
}
