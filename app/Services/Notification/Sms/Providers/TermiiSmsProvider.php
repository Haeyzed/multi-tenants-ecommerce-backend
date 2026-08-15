<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * Termii SMS driver (Nigeria / Africa).
 */
class TermiiSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'termii';
    }

    protected function configKey(): string
    {
        return 'termii';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['api_key'] ?? null)
            && filled($this->sender());
    }

    /**
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $to, string $body): array
    {
        if ($guard = $this->guardEnabled()) {
            return $guard;
        }

        $config = $this->driverConfig();
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.ng.termii.com'), '/');

        try {
            $response = $this->http()
                ->post("{$baseUrl}/api/sms/send", [
                    'api_key' => $config['api_key'],
                    'to' => $to,
                    'from' => $this->sender(),
                    'sms' => $body,
                    'type' => $config['type'] ?? 'plain',
                    'channel' => $config['channel'] ?? 'generic',
                ]);

            if ($response->failed()) {
                return $this->failure($response->json('message') ?? $response->body());
            }

            return $this->success(
                $response->json('message_id')
                    ?? $response->json('messageId')
                    ?? null
            );
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
