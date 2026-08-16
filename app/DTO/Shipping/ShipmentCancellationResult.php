<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

/**
 * Result of cancelling a carrier shipment.
 */
readonly class ShipmentCancellationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
