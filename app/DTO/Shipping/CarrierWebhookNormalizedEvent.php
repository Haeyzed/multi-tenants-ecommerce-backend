<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

use Illuminate\Support\Carbon;

/**
 * Normalized inbound carrier webhook event.
 */
readonly class CarrierWebhookNormalizedEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public string $trackingNumber,
        public ?Carbon $occurredAt = null,
        public array $raw = [],
    ) {}
}
