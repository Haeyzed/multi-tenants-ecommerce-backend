<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Commerce\OrderPaymentService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant Paystack webhook endpoint (no auth; tenancy middleware applies).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly OrderPaymentService $orderPaymentService) {}

    #[Response(
        status: 200,
        description: 'Webhook acknowledged.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function paystack(Request $request): JsonResponse
    {
        $result = $this->orderPaymentService->handleWebhook(
            $request->all(),
            $request->header('x-paystack-signature'),
            $request->getContent(),
        );

        return $this->success($result, 'Webhook processed.');
    }
}
