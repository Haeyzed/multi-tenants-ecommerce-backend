<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\CarrierWebhookProcessorInterface;
use App\DTO\Shipping\CarrierWebhookNormalizedEvent;
use App\Exceptions\Shipping\UnsupportedShippingCarrierException;
use App\Models\Tenant\ShippingCarrierWebhookEvent;
use App\Services\Shipping\Webhooks\FakeCarrierWebhookProcessor;
use App\Services\Tenant\Shipping\ShipmentService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves carrier webhook processors, verifies, and stores events idempotently.
 *
 * Known drivers currently use FakeCarrierWebhookProcessor until live processors exist.
 */
class CarrierWebhookManager
{
    public function __construct(
        private readonly Container $container,
        private readonly ShipmentService $shipments,
    ) {}

    /**
     * Handle an inbound carrier webhook.
     *
     * @return array{
     *     duplicate: bool,
     *     processed: bool,
     *     stored: bool,
     *     shipment_updated: bool,
     *     shipment_id: int|null,
     *     normalized: CarrierWebhookNormalizedEvent|null,
     *     event: ShippingCarrierWebhookEvent|null
     * }
     */
    public function handle(string $carrier, Request $request): array
    {
        $carrier = strtolower(trim($carrier));
        $processor = $this->processor($carrier);

        if (! $processor->verify($request)) {
            throw new AccessDeniedHttpException('Invalid carrier webhook signature.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $normalized = $processor->normalize($payload);

        if (! Schema::hasTable('shipping_carrier_webhook_events')) {
            return [
                'duplicate' => false,
                'processed' => true,
                'stored' => false,
                'shipment_updated' => false,
                'shipment_id' => null,
                'normalized' => $normalized,
                'event' => null,
            ];
        }

        $eventId = $this->resolveEventId($request, $payload, $normalized);

        try {
            $event = ShippingCarrierWebhookEvent::query()->create([
                'provider' => $carrier,
                'event_id' => $eventId,
                'payload' => [
                    'raw' => $payload,
                    'normalized' => [
                        'status' => $normalized->status,
                        'tracking_number' => $normalized->trackingNumber,
                        'occurred_at' => $normalized->occurredAt?->toIso8601String(),
                    ],
                ],
                'processed_at' => now(),
            ]);

            $shipment = $this->shipments->applyCarrierStatus(
                $normalized->trackingNumber,
                $normalized->status,
            );

            return [
                'duplicate' => false,
                'processed' => true,
                'stored' => true,
                'shipment_updated' => $shipment !== null,
                'shipment_id' => $shipment?->id,
                'normalized' => $normalized,
                'event' => $event,
            ];
        } catch (UniqueConstraintViolationException) {
            return [
                'duplicate' => true,
                'processed' => false,
                'stored' => false,
                'shipment_updated' => false,
                'shipment_id' => null,
                'normalized' => $normalized,
                'event' => ShippingCarrierWebhookEvent::query()
                    ->where('provider', $carrier)
                    ->where('event_id', $eventId)
                    ->first(),
            ];
        }
    }

    public function processor(string $carrier): CarrierWebhookProcessorInterface
    {
        $carrier = strtolower(trim($carrier));

        /** @var array<string, mixed> $drivers */
        $drivers = config('shipping.drivers', []);

        if (! array_key_exists($carrier, $drivers)) {
            throw new UnsupportedShippingCarrierException($carrier);
        }

        // Scaffold / known drivers use FakeCarrierWebhookProcessor until live clients exist.
        return $this->container->make(FakeCarrierWebhookProcessor::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventId(
        Request $request,
        array $payload,
        CarrierWebhookNormalizedEvent $normalized,
    ): string {
        $candidates = [
            $request->header('X-Event-Id'),
            $payload['event_id'] ?? null,
            $payload['id'] ?? null,
            $payload['eventId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }

            if (is_int($candidate) || is_float($candidate)) {
                return (string) $candidate;
            }
        }

        $fingerprint = implode('|', [
            $normalized->trackingNumber,
            $normalized->status,
            $normalized->occurredAt?->toIso8601String() ?? '',
            $request->getContent() !== '' ? $request->getContent() : json_encode($payload),
        ]);

        return hash('sha256', $fingerprint);
    }
}
