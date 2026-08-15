<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

/**
 * Result of creating a shipment with a carrier.
 */
readonly class ShipmentCreationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
        public ?string $trackingUrl = null,
        public array $raw = [],
        public ?string $message = null,
    ) {}
}
