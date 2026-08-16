<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentWebhookManager;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant payment provider webhook endpoints (no auth; tenancy middleware applies).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookManager $webhooks) {}

    #[Response(
        status: 200,
        description: 'Webhook acknowledged.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $result = $this->webhooks->handle($provider, $request);

        return $this->success($result, 'Webhook processed.');
    }

    /**
     * Backward-compatible Paystack-only route.
     */
    #[Response(
        status: 200,
        description: 'Webhook acknowledged.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function paystack(Request $request): JsonResponse
    {
        return $this->__invoke($request, 'paystack');
    }
}
