<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Contracts\Notification\SmsProvider;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder SMS provider that logs and skips until a real driver is configured.
 */
class NullSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $to, string $body): array
    {
        Log::info('Null SMS provider skipped send', [
            'to' => $to,
            'body' => $body,
        ]);

        return [
            'success' => false,
            'error' => 'SMS provider is not configured.',
        ];
    }
}
