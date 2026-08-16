<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ShippingCarrierWebhookEvent;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound carrier webhook stub (no auth; signature verification placeholder).
 */
class CarrierWebhookController extends Controller
{
    #[Response(
        status: 200,
        description: 'Webhook acknowledged.',
        type: 'array{success: true, message: string, data: array{stored: bool, duplicate: bool}, meta: null, errors: null}',
    )]
    public function __invoke(Request $request, string $carrier): JsonResponse
    {
        // Signature verification stub — replace with real carrier HMAC checks when integrating.
        $signature = $request->header('X-Shipping-Signature')
            ?? $request->header('X-Carrier-Signature');

        unset($signature);

        if (! Schema::hasTable('shipping_carrier_webhook_events')) {
            return $this->success([
                'stored' => false,
                'duplicate' => false,
            ], 'Webhook acknowledged (storage unavailable).');
        }

        $payload = $request->all();
        $eventId = $this->resolveEventId($request, $payload);
        $provider = strtolower(trim($carrier));

        try {
            ShippingCarrierWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'payload' => $payload,
                'processed_at' => null,
            ]);

            return $this->success([
                'stored' => true,
                'duplicate' => false,
            ], 'Webhook stored.');
        } catch (UniqueConstraintViolationException) {
            return $this->success([
                'stored' => false,
                'duplicate' => true,
            ], 'Webhook already recorded.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventId(Request $request, array $payload): string
    {
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

        return hash('sha256', $request->getContent() !== '' ? $request->getContent() : json_encode($payload));
    }
}
