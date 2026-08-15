<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Contracts\Notification\SmsProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared helpers for HTTP-based SMS drivers.
 */
abstract class AbstractHttpSmsProvider implements SmsProvider
{
    abstract protected function configKey(): string;

    public function isEnabled(): bool
    {
        if (! (bool) config('notifications.sms.enabled')) {
            return false;
        }

        return $this->credentialsConfigured();
    }

    /**
     * Whether driver-specific credentials are present.
     */
    abstract protected function credentialsConfigured(): bool;

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    protected function failure(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
        ];
    }

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    protected function success(?string $messageId = null): array
    {
        return [
            'success' => true,
            'message_id' => $messageId,
        ];
    }

    protected function timeout(): int
    {
        return (int) config('notifications.sms.timeout', 15);
    }

    protected function connectTimeout(): int
    {
        return (int) config('notifications.sms.connect_timeout', 5);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function driverConfig(array $defaults = []): array
    {
        /** @var array<string, mixed> $config */
        $config = config('notifications.sms.drivers.'.$this->configKey(), []);

        return array_merge($defaults, $config);
    }

    protected function sender(?string $fallback = null): ?string
    {
        $from = $this->driverConfig()['from'] ?? config('notifications.sms.from');

        if (filled($from)) {
            return (string) $from;
        }

        return $fallback;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->acceptJson();
    }

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    protected function guardEnabled(): ?array
    {
        if ($this->isEnabled()) {
            return null;
        }

        return $this->failure('SMS driver ['.$this->name().'] is disabled or missing credentials.');
    }

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    protected function reportFailure(Throwable $exception): array
    {
        Log::warning('SMS send failed', [
            'provider' => $this->name(),
            'error' => $exception->getMessage(),
        ]);

        return $this->failure($exception->getMessage());
    }
}
