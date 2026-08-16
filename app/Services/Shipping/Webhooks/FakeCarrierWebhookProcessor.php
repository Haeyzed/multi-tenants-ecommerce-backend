<?php

declare(strict_types=1);

namespace App\Services\Shipping\Webhooks;

use App\Contracts\Shipping\CarrierWebhookProcessorInterface;
use App\DTO\Shipping\CarrierWebhookNormalizedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Always-accepting fake carrier webhook processor for tests and local use.
 */
class FakeCarrierWebhookProcessor implements CarrierWebhookProcessorInterface
{
    public function carrier(): string
    {
        return 'fake';
    }

    public function verify(Request $request): bool
    {
        return true;
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
