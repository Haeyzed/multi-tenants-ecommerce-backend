<?php

declare(strict_types=1);

namespace App\Services\Notification\Push;

use App\Contracts\Notification\PushNotificationProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging HTTP v1 provider (no Composer SDK).
 */
class FcmPushProvider implements PushNotificationProvider
{
    public function isEnabled(): bool
    {
        if (! (bool) config('notifications.fcm.enabled')) {
            return false;
        }

        return filled(config('notifications.fcm.project_id'))
            && filled(config('notifications.fcm.client_email'))
            && filled(config('notifications.fcm.private_key'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'FCM is disabled or missing credentials.',
            ];
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = (string) config('notifications.fcm.project_id');
            $timeout = (int) config('notifications.fcm.timeout', 15);

            $response = Http::timeout($timeout)
                ->withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map(
                            static fn ($value) => is_string($value) ? $value : (string) json_encode($value),
                            $data,
                        ),
                    ],
                ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => $response->json('error.message') ?? $response->body(),
                ];
            }

            return [
                'success' => true,
                'message_id' => $response->json('name'),
            ];
        } catch (Throwable $exception) {
            Log::warning('FCM push failed', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Obtain a short-lived OAuth access token using a service account JWT.
     */
    protected function accessToken(): string
    {
        return Cache::remember('notifications.fcm.access_token', 3500, function (): string {
            $clientEmail = (string) config('notifications.fcm.client_email');
            $privateKey = str_replace('\\n', "\n", (string) config('notifications.fcm.private_key'));
            $now = time();

            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claim = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            $unsigned = $header.'.'.$claim;
            $signature = '';
            $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            if (! $ok) {
                throw new \RuntimeException('Unable to sign FCM service account JWT.');
            }

            $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed() || blank($response->json('access_token'))) {
                throw new \RuntimeException('Unable to obtain FCM access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
