<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * Twilio Programmable Messaging SMS driver.
 */
class TwilioSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'twilio';
    }

    protected function configKey(): string
    {
        return 'twilio';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['account_sid'] ?? null)
            && filled($config['auth_token'] ?? null)
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
        $sid = (string) $config['account_sid'];
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.twilio.com'), '/');

        try {
            $response = $this->http()
                ->withBasicAuth($sid, (string) $config['auth_token'])
                ->asForm()
                ->post("{$baseUrl}/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $this->sender(),
                    'Body' => $body,
                ]);

            if ($response->failed()) {
                return $this->failure($response->json('message') ?? $response->body());
            }

            return $this->success($response->json('sid'));
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
