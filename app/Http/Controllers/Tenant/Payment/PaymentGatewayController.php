<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Payment\UpsertPaymentGatewayRequest;
use App\Http\Resources\Tenant\Payment\TenantPaymentGatewayResource;
use App\Services\Tenant\Payment\TenantPaymentGatewayService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant admin endpoints for payment gateway configuration.
 */
class PaymentGatewayController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  TenantPaymentGatewayService  $gateways
     */
    public function __construct(private readonly TenantPaymentGatewayService $gateways) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Configured payment gateways.', type: 'array{success: true, message: string, data: TenantPaymentGatewayResource[], meta: null, errors: null}')]
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user('tenant')?->can('payments.manage')
                || $request->user('tenant')?->can('payment_gateways.view')
                || $request->user('tenant')?->can('payment_gateways.manage'),
            403,
        );

        return $this->success(
            TenantPaymentGatewayResource::collection($this->gateways->list()->all()),
            'Payment gateways retrieved successfully.',
        );
    }

    /**
     * Upsert.
     *
     * @param  UpsertPaymentGatewayRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Upserted payment gateway.', type: 'array{success: true, message: string, data: TenantPaymentGatewayResource, meta: null, errors: null}')]
    public function upsert(UpsertPaymentGatewayRequest $request): JsonResponse
    {
        return $this->success(
            new TenantPaymentGatewayResource($this->gateways->upsert($request->validated())),
            'Payment gateway saved successfully.',
        );
    }

    /**
     * Enable.
     *
     * @param  Request  $request
     * @param  string  $gateway
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Enabled payment gateway.', type: 'array{success: true, message: string, data: TenantPaymentGatewayResource, meta: null, errors: null}')]
    public function enable(Request $request, string $gateway): JsonResponse
    {
        abort_unless(
            $request->user('tenant')?->can('payments.manage')
                || $request->user('tenant')?->can('payment_gateways.manage'),
            403,
        );

        return $this->success(
            new TenantPaymentGatewayResource($this->gateways->enable($gateway)),
            'Payment gateway enabled successfully.',
        );
    }

    /**
     * Disable.
     *
     * @param  Request  $request
     * @param  string  $gateway
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Disabled payment gateway.', type: 'array{success: true, message: string, data: TenantPaymentGatewayResource, meta: null, errors: null}')]
    public function disable(Request $request, string $gateway): JsonResponse
    {
        abort_unless(
            $request->user('tenant')?->can('payments.manage')
                || $request->user('tenant')?->can('payment_gateways.manage'),
            403,
        );

        return $this->success(
            new TenantPaymentGatewayResource($this->gateways->disable($gateway)),
            'Payment gateway disabled successfully.',
        );
    }
}
