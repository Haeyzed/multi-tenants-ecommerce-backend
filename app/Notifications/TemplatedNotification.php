<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification used for database and mail channel delivery.
 *
 * Queuing is handled by SendNotificationJob / NotificationService; channels
 * deliver immediately via notifyNow once resolved.
 */
class TemplatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $via
     */
    public function __construct(
        public readonly NotificationPayload $payload,
        public readonly array $via = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($this->via !== []) {
            return $this->via;
        }

        return array_values(array_intersect(
            $this->payload->channels,
            [NotificationChannel::Database->value, NotificationChannel::Email->value],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->payload->key,
            'title' => $this->payload->content['title'] ?? null,
            'body' => $this->payload->content['body'] ?? null,
            'data' => $this->payload->data,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = (string) ($this->payload->content['email_subject'] ?? $this->payload->content['title'] ?? 'Notification');
        $body = (string) ($this->payload->content['email_body'] ?? $this->payload->content['body'] ?? '');

        return (new MailMessage)
            ->subject($subject)
            ->line($body);
    }
}
