<?php

declare(strict_types=1);

namespace App\DTO\Shipping;

/**
 * A shipping rate quote from a carrier.
 */
readonly class ShippingRateQuote
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $serviceCode,
        public string $serviceName,
        public string $amount,
        public string $currency,
        public ?int $estimatedDaysMin = null,
        public ?int $estimatedDaysMax = null,
        public array $raw = [],
    ) {}
}
