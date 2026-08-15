<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * Vonage (Nexmo) SMS driver.
 */
class VonageSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'vonage';
    }

    protected function configKey(): string
    {
        return 'vonage';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['api_key'] ?? null)
            && filled($config['api_secret'] ?? null)
            && filled($this->sender('Vonage'));
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
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rest.nexmo.com'), '/');

        try {
            $response = $this->http()
                ->asForm()
                ->post("{$baseUrl}/sms/json", [
                    'api_key' => $config['api_key'],
                    'api_secret' => $config['api_secret'],
                    'to' => ltrim($to, '+'),
                    'from' => $this->sender('Vonage'),
                    'text' => $body,
                ]);

            if ($response->failed()) {
                return $this->failure($response->json('error_text') ?? $response->body());
            }

            $messages = $response->json('messages') ?? [];
            $first = is_array($messages) ? ($messages[0] ?? []) : [];
            $status = (string) ($first['status'] ?? '');

            if ($status !== '0') {
                return $this->failure((string) ($first['error-text'] ?? $first['error_text'] ?? 'Vonage SMS failed.'));
            }

            return $this->success(isset($first['message-id']) ? (string) $first['message-id'] : null);
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
