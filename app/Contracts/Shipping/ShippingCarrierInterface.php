<?php

declare(strict_types=1);

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShipmentCancellationResult;
use App\DTO\Shipping\ShipmentCreationResult;
use App\DTO\Shipping\ShipmentLabelResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\DTO\Shipping\ShippingRateQuote;
use App\Services\Shipping\CarrierWebhookManager;
use App\Services\Shipping\ShippingCarrierManager;

/**
 * Contract for external shipping carrier integrations.
 *
 * Partner implementors should also implement {@see CarrierWebhookProcessorInterface}
 * and register both in {@see ShippingCarrierManager} and
 * {@see CarrierWebhookManager} when shipping live clients.
 */
interface ShippingCarrierInterface
{
    /**
     * Fetch rate quotes for a shipment context.
     *
     * Expected context keys (all optional unless noted by the carrier):
     * - currency?: string
     * - weight?: float|string
     * - destination?: array<string, mixed>|string
     *
     * @param  array{
     *     currency?: string,
     *     weight?: float|string,
     *     destination?: array<string, mixed>|string
     * }  $context
     * @return list<ShippingRateQuote>
     */
    public function getRates(array $context): array;

    /**
     * Create a shipment with the carrier.
     *
     * Expected data keys:
     * - order_id: int|string
     * - order_number: string
     * - shipping_method_code: string
     * - shipping_address?: array<string, mixed>
     *
     * @param  array{
     *     order_id: int|string,
     *     order_number: string,
     *     shipping_method_code: string,
     *     shipping_address?: array<string, mixed>
     * }  $data
     */
    public function createShipment(array $data): ShipmentCreationResult;

    /**
     * Track an existing shipment.
     */
    public function trackShipment(string $trackingNumber): ShipmentTrackingResult;

    /**
     * Cancel an existing shipment with the carrier.
     *
     * @param  array<string, mixed>  $context
     */
    public function cancelShipment(string $trackingNumber, array $context = []): ShipmentCancellationResult;

    /**
     * Fetch a printable shipping label for a tracking number.
     *
     * @param  array<string, mixed>  $context
     */
    public function getLabel(string $trackingNumber, array $context = []): ShipmentLabelResult;
}
