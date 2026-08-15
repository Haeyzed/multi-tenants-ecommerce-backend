<?php

declare(strict_types=1);

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShipmentCreationResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\DTO\Shipping\ShippingRateQuote;

/**
 * Contract for external shipping carrier integrations.
 */
interface ShippingCarrierInterface
{
    /**
     * Fetch rate quotes for a shipment context.
     *
     * @param  array<string, mixed>  $context
     * @return list<ShippingRateQuote>
     */
    public function getRates(array $context): array;

    /**
     * Create a shipment with the carrier.
     *
     * @param  array<string, mixed>  $data
     */
    public function createShipment(array $data): ShipmentCreationResult;

    /**
     * Track an existing shipment.
     */
    public function trackShipment(string $trackingNumber): ShipmentTrackingResult;
}
