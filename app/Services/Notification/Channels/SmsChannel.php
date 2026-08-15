<?php

declare(strict_types=1);

namespace App\Services\Notification\Channels;

use App\Contracts\Notification\NotificationChannel;
use App\Contracts\Notification\SmsProvider;
use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\DeliveryStatus;
use App\Enums\Notification\NotificationChannel as NotificationChannelEnum;
use App\Models\Notification\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivers SMS through the configured SMS provider.
 */
class SmsChannel implements NotificationChannel
{
    public function __construct(private readonly SmsProvider $smsProvider) {}

    public function name(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Sms;
    }

    public function send(Model $notifiable, NotificationPayload $payload): NotificationDelivery
    {
        $phone = data_get($notifiable, 'phone');

        if (! is_string($phone) || $phone === '') {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Sms,
                'status' => DeliveryStatus::Skipped,
                'provider' => 'sms',
                'error' => 'Notifiable has no phone number.',
            ]);
        }

        if (! $this->smsProvider->isEnabled()) {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Sms,
                'status' => DeliveryStatus::Skipped,
                'provider' => $this->smsProvider->name(),
                'error' => 'SMS provider disabled or not configured.',
            ]);
        }

        $body = (string) ($payload->content['sms_body'] ?? $payload->content['body'] ?? '');
        $result = $this->smsProvider->send($phone, $body);

        return NotificationDelivery::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_key' => $payload->key,
            'channel' => NotificationChannelEnum::Sms,
            'status' => $result['success'] ? DeliveryStatus::Sent : DeliveryStatus::Failed,
            'provider' => $this->smsProvider->name(),
            'provider_message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
            'sent_at' => ($result['success'] ?? false) ? now() : null,
            'failed_at' => ($result['success'] ?? false) ? null : now(),
        ]);
    }
}
