<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\CarrierWebhookProcessorInterface;
use App\DTO\Shipping\CarrierWebhookNormalizedEvent;
use App\Models\Tenant\ShippingCarrierWebhookEvent;
use App\Services\Shipping\Webhooks\FakeCarrierWebhookProcessor;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves carrier webhook processors, verifies, and stores events idempotently.
 */
class CarrierWebhookManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * Handle an inbound carrier webhook.
     *
     * @return array{
     *     duplicate: bool,
     *     processed: bool,
     *     stored: bool,
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

            return [
                'duplicate' => false,
                'processed' => true,
                'stored' => true,
                'normalized' => $normalized,
                'event' => $event,
            ];
        } catch (UniqueConstraintViolationException) {
            return [
                'duplicate' => true,
                'processed' => false,
                'stored' => false,
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

        return match ($carrier) {
            'fake', 'dhl', 'gig', 'fedex', 'ups', 'local' => $this->container->make(FakeCarrierWebhookProcessor::class),
            default => throw new InvalidArgumentException("Unsupported shipping carrier webhook [{$carrier}]."),
        };
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
