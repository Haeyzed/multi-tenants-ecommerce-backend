<?php

declare(strict_types=1);

namespace App\Contracts\Notification;

/**
 * Contract for SMS providers.
 */
interface SmsProvider
{
    /**
     * Driver name used in delivery audit records.
     */
    public function name(): string;

    /**
     * Whether the provider is configured and enabled.
     */
    public function isEnabled(): bool;

    /**
     * Send an SMS message.
     *
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $to, string $body): array;
}
