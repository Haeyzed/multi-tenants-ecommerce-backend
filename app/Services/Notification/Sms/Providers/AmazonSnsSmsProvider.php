<?php

declare(strict_types=1);

namespace App\Services\Notification\Sms\Providers;

use App\Services\Notification\Sms\AbstractHttpSmsProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Amazon SNS SMS driver (SigV4 HTTP, no AWS SDK).
 */
class AmazonSnsSmsProvider extends AbstractHttpSmsProvider
{
    public function name(): string
    {
        return 'amazon_sns';
    }

    protected function configKey(): string
    {
        return 'amazon_sns';
    }

    protected function credentialsConfigured(): bool
    {
        $config = $this->driverConfig();

        return filled($config['access_key_id'] ?? null)
            && filled($config['secret_access_key'] ?? null)
            && filled($config['region'] ?? null);
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
        $region = (string) $config['region'];
        $host = "sns.{$region}.amazonaws.com";
        $endpoint = "https://{$host}/";

        $payload = [
            'Action' => 'Publish',
            'Version' => '2010-03-31',
            'PhoneNumber' => $to,
            'Message' => $body,
        ];

        $sender = $this->sender();
        if ($sender !== null) {
            $payload['MessageAttributes.entry.1.Name'] = 'AWS.SNS.SMS.SenderID';
            $payload['MessageAttributes.entry.1.Value.DataType'] = 'String';
            $payload['MessageAttributes.entry.1.Value.StringValue'] = $sender;
        }

        try {
            $headers = $this->signHeaders(
                method: 'POST',
                host: $host,
                region: $region,
                payload: http_build_query($payload),
                accessKeyId: (string) $config['access_key_id'],
                secretAccessKey: (string) $config['secret_access_key'],
            );

            $response = Http::timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->withHeaders($headers)
                ->asForm()
                ->post($endpoint, $payload);

            if ($response->failed()) {
                return $this->failure($response->body());
            }

            preg_match('/<MessageId>([^<]+)<\/MessageId>/', $response->body(), $matches);

            return $this->success($matches[1] ?? null);
        } catch (Throwable $exception) {
            return $this->reportFailure($exception);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function signHeaders(
        string $method,
        string $host,
        string $region,
        string $payload,
        string $accessKeyId,
        string $secretAccessKey,
    ): array {
        $service = 'sns';
        $algorithm = 'AWS4-HMAC-SHA256';
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $payloadHash = hash('sha256', $payload);
        $canonicalHeaders = "content-type:application/x-www-form-urlencoded\nhost:{$host}\nx-amz-date:{$now}\n";
        $signedHeaders = 'content-type;host;x-amz-date';
        $canonicalRequest = implode("\n", [
            $method,
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $now,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $date, 'AWS4'.$secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host' => $host,
            'X-Amz-Date' => $now,
            'Authorization' => "{$algorithm} Credential={$accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}",
        ];
    }
}
