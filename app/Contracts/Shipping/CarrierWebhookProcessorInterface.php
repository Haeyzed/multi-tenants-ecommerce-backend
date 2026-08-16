<?php

declare(strict_types=1);

namespace App\Contracts\Shipping;

use App\DTO\Shipping\CarrierWebhookNormalizedEvent;
use Illuminate\Http\Request;

/**
 * Verifies and normalizes inbound carrier webhook payloads.
 */
interface CarrierWebhookProcessorInterface
{
    /**
     * Carrier key this processor handles (e.g. fake, dhl).
     */
    public function carrier(): string;

    /**
     * Verify the inbound webhook request (signature / authenticity).
     */
    public function verify(Request $request): bool;

    /**
     * Normalize a raw payload into a carrier-agnostic event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): CarrierWebhookNormalizedEvent;
}
