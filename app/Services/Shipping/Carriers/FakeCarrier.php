<?php

declare(strict_types=1);

namespace App\Services\Shipping\Carriers;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\DTO\Shipping\ShipmentCreationResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\DTO\Shipping\ShippingRateQuote;

/**
 * Deterministic fake carrier for tests and local development.
 */
class FakeCarrier implements ShippingCarrierInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return list<ShippingRateQuote>
     */
    public function getRates(array $context): array
    {
        $currency = (string) ($context['currency'] ?? 'NGN');

        return [
            new ShippingRateQuote(
                serviceCode: 'fake-standard',
                serviceName: 'Fake Standard',
                amount: '500.00',
                currency: $currency,
                estimatedDaysMin: 2,
                estimatedDaysMax: 5,
            ),
            new ShippingRateQuote(
                serviceCode: 'fake-express',
                serviceName: 'Fake Express',
                amount: '1500.00',
                currency: $currency,
                estimatedDaysMin: 1,
                estimatedDaysMax: 2,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createShipment(array $data): ShipmentCreationResult
    {
        $tracking = 'FAKE-'.strtoupper(substr(sha1(json_encode($data)), 0, 12));

        return new ShipmentCreationResult(
            successful: true,
            trackingNumber: $tracking,
            carrier: 'fake',
            trackingUrl: 'https://example.test/track/'.$tracking,
            raw: ['tracking_number' => $tracking],
        );
    }

    public function trackShipment(string $trackingNumber): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            found: true,
            status: 'in_transit',
            trackingUrl: 'https://example.test/track/'.$trackingNumber,
            raw: ['tracking_number' => $trackingNumber, 'status' => 'in_transit'],
        );
    }
}
