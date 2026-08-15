<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * MessageBird / Bird SMS driver.
 */
class MessageBirdSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'messagebird';
    }

    protected function configKey(): string
    {
        return 'messagebird';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['access_key'] ?? null)
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
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rest.messagebird.com'), '/');

        try {
            $response = $this->http()
                ->withHeaders([
                    'Authorization' => 'AccessKey '.$config['access_key'],
                ])
                ->asForm()
                ->post("{$baseUrl}/messages", [
                    'originator' => $this->sender(),
                    'recipients' => $to,
                    'body' => $body,
                ]);

            if ($response->failed()) {
                return $this->failure(
                    $response->json('errors.0.description')
                        ?? $response->json('message')
                        ?? $response->body()
                );
            }

            return $this->success($response->json('id'));
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
