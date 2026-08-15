<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Throwable;

/**
 * Hubtel SMS driver (Ghana / West Africa).
 */
class HubtelSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'hubtel';
    }

    protected function configKey(): string
    {
        return 'hubtel';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
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
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://sms.hubtel.com'), '/');

        try {
            $response = $this->http()
                ->withBasicAuth((string) $config['client_id'], (string) $config['client_secret'])
                ->get("{$baseUrl}/v1/messages/send", [
                    'From' => $this->sender(),
                    'To' => $to,
                    'Content' => $body,
                ]);

            if ($response->failed()) {
                return $this->failure(
                    $response->json('statusDescription')
                        ?? $response->json('message')
                        ?? $response->body()
                );
            }

            $statusCode = (string) ($response->json('statusCode') ?? $response->json('ResponseCode') ?? '0');
            if (! in_array($statusCode, ['0', '0000'], true) && $response->json('MessageId') === null && $response->json('data.MessageId') === null) {
                return $this->failure(
                    $response->json('statusDescription')
                        ?? $response->json('StatusDescription')
                        ?? 'Hubtel SMS failed.'
                );
            }

            return $this->success(
                $response->json('MessageId')
                    ?? $response->json('data.MessageId')
                    ?? $response->json('messageId')
            );
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }
}
