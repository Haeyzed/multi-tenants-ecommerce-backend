<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * Africa's Talking SMS driver.
 */
class AfricasTalkingSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'africastalking';
    }

    protected function configKey(): string
    {
        return 'africastalking';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['username'] ?? null)
            && filled($config['api_key'] ?? null)
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
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.africastalking.com'), '/');

        try {
            $response = $this->http()
                ->withHeaders([
                    'apiKey' => (string) $config['api_key'],
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post("{$baseUrl}/version1/messaging", [
                    'username' => $config['username'],
                    'to' => $to,
                    'message' => $body,
                    'from' => $this->sender(),
                ]);

            if ($response->failed()) {
                return $this->failure($response->json('message') ?? $response->body());
            }

            $recipients = $response->json('SMSMessageData.Recipients') ?? [];
            $first = is_array($recipients) ? ($recipients[0] ?? []) : [];
            $statusCode = (int) ($first['statusCode'] ?? 0);

            if ($statusCode >= 400 || $statusCode === 0 && blank($first['messageId'] ?? null)) {
                return $this->failure((string) ($first['status'] ?? 'Africa\'s Talking SMS failed.'));
            }

            return $this->success(isset($first['messageId']) ? (string) $first['messageId'] : null);
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
