<?php

declare(strict_types=1);

namespace App\Services\Shipping\Webhooks;

use App\Contracts\Shipping\CarrierWebhookProcessorInterface;
use App\DTO\Shipping\CarrierWebhookNormalizedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fake carrier webhook processor for tests and local use.
 *
 * When `shipping.drivers.fake.webhook_secret` is a non-empty string, requires
 * header `X-Fake-Signature` = HMAC-SHA256 of the raw body. When the secret is
 * empty/null, verification always succeeds (backwards compatible).
 */
class FakeCarrierWebhookProcessor implements CarrierWebhookProcessorInterface
{
    public function carrier(): string
    {
        return 'fake';
    }

    public function verify(Request $request): bool
    {
        $secret = config('shipping.drivers.fake.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return true;
        }

        $signature = $request->header('X-Fake-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): CarrierWebhookNormalizedEvent
    {
        $tracking = $payload['tracking_number']
            ?? $payload['trackingNumber']
            ?? $payload['tracking']
            ?? '';

        $occurredAt = null;
        $occurredRaw = $payload['occurred_at'] ?? $payload['occurredAt'] ?? null;
        if (is_string($occurredRaw) && $occurredRaw !== '') {
            $occurredAt = Carbon::parse($occurredRaw);
        }

        return new CarrierWebhookNormalizedEvent(
            status: (string) ($payload['status'] ?? 'unknown'),
            trackingNumber: (string) $tracking,
            occurredAt: $occurredAt,
            raw: $payload,
        );
    }
}
