<?php

declare(strict_types=1);

namespace App\Contracts\Notification;

/**
 * Contract for push notification providers (e.g. FCM).
 */
interface PushNotificationProvider
{
    /**
     * Whether the provider is configured and enabled.
     */
    public function isEnabled(): bool;

    /**
     * Send a push notification to a device token.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): array;
}
