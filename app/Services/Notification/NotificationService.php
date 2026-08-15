<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\DTO\Notification\NotificationPayload;
use App\Jobs\SendNotificationJob;
use App\Models\Notification\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates template lookup, preference filtering, and channel delivery.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TemplateRenderer $renderer,
        private readonly ChannelResolver $channels,
    ) {}

    /**
     * Send a templated notification to a notifiable model.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $onlyChannels
     * @return list<NotificationDelivery>
     */
    public function send(Model $notifiable, string $key, array $data = [], ?array $onlyChannels = null): array
    {
        if (config('notifications.queue')) {
            SendNotificationJob::dispatch(
                $notifiable->getMorphClass(),
                $notifiable->getKey(),
                $key,
                $data,
                $onlyChannels,
                tenancy()->initialized ? tenancy()->tenant->getTenantKey() : null,
            );

            return [];
        }

        return $this->sendNow($notifiable, $key, $data, $onlyChannels);
    }

    /**
     * Deliver immediately without queueing.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $onlyChannels
     * @return list<NotificationDelivery>
     */
    public function sendNow(Model $notifiable, string $key, array $data = [], ?array $onlyChannels = null): array
    {
        $template = $this->templates->findActiveByKey($key);

        if ($template === null) {
            Log::warning('Notification template missing or inactive', ['key' => $key]);

            return [];
        }

        /** @var list<string> $templateChannels */
        $templateChannels = $template->channels ?? [];

        if ($onlyChannels !== null) {
            $templateChannels = array_values(array_intersect($templateChannels, $onlyChannels));
        }

        /** @var list<string> $variables */
        $variables = $template->variables ?? [];

        $content = [
            'title' => $this->renderer->render($template->title, $data, $variables),
            'body' => $this->renderer->render($template->body, $data, $variables),
            'email_subject' => $this->renderer->render($template->email_subject, $data, $variables),
            'email_body' => $this->renderer->render($template->email_body, $data, $variables),
            'push_title' => $this->renderer->render($template->push_title, $data, $variables),
            'push_body' => $this->renderer->render($template->push_body, $data, $variables),
            'sms_body' => $this->renderer->render($template->sms_body, $data, $variables),
        ];

        $payload = new NotificationPayload(
            key: $template->key,
            data: $data,
            channels: $templateChannels,
            content: $content,
            isMandatory: $template->is_mandatory,
        );

        $resolved = $this->channels->resolve(
            $notifiable,
            $template->key,
            $templateChannels,
            $template->is_mandatory,
        );

        $deliveries = [];

        foreach ($resolved as $channel) {
            $deliveries[] = $channel->send($notifiable, $payload);
        }

        return $deliveries;
    }
}
