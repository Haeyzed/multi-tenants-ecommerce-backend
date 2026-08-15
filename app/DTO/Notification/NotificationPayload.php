<?php

declare(strict_types=1);

namespace App\DTO\Notification;

/**
 * Rendered notification content ready for channel delivery.
 *
 * @phpstan-type ChannelContent array{
 *     title?: string|null,
 *     body?: string|null,
 *     email_subject?: string|null,
 *     email_body?: string|null,
 *     push_title?: string|null,
 *     push_body?: string|null,
 *     sms_body?: string|null
 * }
 */
readonly class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $channels
     * @param  ChannelContent  $content
     */
    public function __construct(
        public string $key,
        public array $data,
        public array $channels,
        public array $content,
        public bool $isMandatory = false,
    ) {}
}
