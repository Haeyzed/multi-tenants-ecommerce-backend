<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Shipping;

use App\Http\Controllers\Controller;
use App\Services\Shipping\CarrierWebhookManager;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound carrier webhook endpoint.
 */
class CarrierWebhookController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CarrierWebhookManager  $webhooks
     */
    public function __construct(private readonly CarrierWebhookManager $webhooks) {}

    /**
     * __invoke.
     *
     * @param  Request  $request
     * @param  string  $carrier
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Webhook acknowledged.',
        type: 'array{success: true, message: string, data: array{stored: bool, duplicate: bool, processed: bool}, meta: null, errors: null}',
    )]
    public function __invoke(Request $request, string $carrier): JsonResponse
    {
        if (! Schema::hasTable('shipping_carrier_webhook_events')) {
            return $this->success([
                'stored' => false,
                'duplicate' => false,
                'processed' => true,
            ], 'Webhook acknowledged (storage unavailable).');
        }

        $result = $this->webhooks->handle($carrier, $request);

        $message = $result['duplicate']
            ? 'Webhook already recorded.'
            : ($result['stored'] ? 'Webhook stored.' : 'Webhook processed.');

        return $this->success([
            'stored' => $result['stored'],
            'duplicate' => $result['duplicate'],
            'processed' => $result['processed'],
        ], $message);
    }
}
