<?php

declare(strict_types=1);

namespace App\Services\Notification\Channels;

use App\Contracts\Notification\NotificationChannel;
use App\Contracts\Notification\PushNotificationProvider;
use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\DeliveryStatus;
use App\Enums\Notification\NotificationChannel as NotificationChannelEnum;
use App\Models\Notification\DeviceToken;
use App\Models\Notification\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivers push notifications through the configured push provider.
 */
class PushChannel implements NotificationChannel
{
    public function __construct(private readonly PushNotificationProvider $pushProvider) {}

    public function name(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Push;
    }

    public function send(Model $notifiable, NotificationPayload $payload): NotificationDelivery
    {
        if (! $this->pushProvider->isEnabled()) {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Push,
                'status' => DeliveryStatus::Skipped,
                'provider' => 'fcm',
                'error' => 'Push provider disabled or not configured.',
            ]);
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $notifiable->getKey())
            ->active()
            ->get();

        if ($tokens->isEmpty()) {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
                'notification_key' => $payload->key,
                'channel' => NotificationChannelEnum::Push,
                'status' => DeliveryStatus::Skipped,
                'provider' => 'fcm',
                'error' => 'No active device tokens.',
            ]);
        }

        $title = (string) ($payload->content['push_title'] ?? $payload->content['title'] ?? '');
        $body = (string) ($payload->content['push_body'] ?? $payload->content['body'] ?? '');
        $errors = [];
        $messageIds = [];
        $sent = 0;

        foreach ($tokens as $token) {
            $result = $this->pushProvider->send(
                $token->device_token,
                $title,
                $body,
                [
                    'notification_key' => $payload->key,
                    ...array_map(static fn ($value) => is_scalar($value) ? (string) $value : json_encode($value), $payload->data),
                ],
            );

            if ($result['success']) {
                $sent++;
                if (! empty($result['message_id'])) {
                    $messageIds[] = $result['message_id'];
                }
                $token->forceFill(['last_used_at' => now()])->save();
            } else {
                $errors[] = $result['error'] ?? 'Unknown push error';
            }
        }

        $status = $sent > 0
            ? DeliveryStatus::Sent
            : DeliveryStatus::Failed;

        return NotificationDelivery::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_key' => $payload->key,
            'channel' => NotificationChannelEnum::Push,
            'status' => $status,
            'provider' => 'fcm',
            'provider_message_id' => $messageIds[0] ?? null,
            'error' => $errors === [] ? null : implode('; ', $errors),
            'metadata' => [
                'sent_count' => $sent,
                'token_count' => $tokens->count(),
                'message_ids' => $messageIds,
            ],
            'sent_at' => $status === DeliveryStatus::Sent ? now() : null,
            'failed_at' => $status === DeliveryStatus::Failed ? now() : null,
        ]);
    }
}
