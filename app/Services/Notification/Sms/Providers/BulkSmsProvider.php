<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * BulkSMS.com REST SMS driver.
 */
class BulkSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'bulksms';
    }

    protected function configKey(): string
    {
        return 'bulksms';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['token_id'] ?? null)
            && filled($config['token_secret'] ?? null);
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
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.bulksms.com'), '/');

        $payload = [
            'to' => $to,
            'body' => $body,
        ];

        $sender = $this->sender();
        if ($sender !== null) {
            $payload['from'] = $sender;
        }

        try {
            $response = $this->http()
                ->withBasicAuth((string) $config['token_id'], (string) $config['token_secret'])
                ->post("{$baseUrl}/v1/messages", [$payload]);

            if ($response->failed()) {
                return $this->failure(
                    $response->json('detail')
                        ?? $response->json('title')
                        ?? $response->body()
                );
            }

            $data = $response->json();
            $first = is_array($data) ? ($data[0] ?? $data) : [];

            return $this->success(isset($first['id']) ? (string) $first['id'] : null);
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
