<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

/**
 * Tracking information for a carrier shipment.
 */
readonly class ShipmentTrackingResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $found,
        public ?string $status = null,
        public ?string $trackingUrl = null,
        public array $raw = [],
        public ?string $message = null,
    ) {}
}
