<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\InitializeOrderPaymentRequest;
use App\Http\Requests\Tenant\Commerce\VerifyOrderPaymentRequest;
use App\Http\Resources\Tenant\Commerce\OrderPaymentResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\OrderPaymentService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer order payment initiation and verification.
 */
class PaymentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  OrderPaymentService  $orderPaymentService
     */
    public function __construct(private readonly OrderPaymentService $orderPaymentService) {}

    /**
     * Pay.
     *
     * @param  InitializeOrderPaymentRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Payment initiation result.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function pay(InitializeOrderPaymentRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $order = Order::query()->findOrFail($request->validated('order_id'));

        $result = $this->orderPaymentService->initialize($order, $customer);

        return $this->created([
            'authorization_url' => $result['initiation']->authorizationUrl,
            'access_code' => $result['initiation']->accessCode,
            'reference' => $result['initiation']->reference,
            'provider' => $result['initiation']->provider,
            'payment' => new OrderPaymentResource($result['payment']),
        ], 'Payment initialized successfully.');
    }

    /**
     * Verify.
     *
     * @param  VerifyOrderPaymentRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Verified payment.',
        type: 'array{success: true, message: string, data: OrderPaymentResource, meta: null, errors: null}',
    )]
    public function verify(VerifyOrderPaymentRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $payment = $this->orderPaymentService->verifyForCustomer(
            $request->validated('reference'),
            $customer,
        );

        return $this->success(
            new OrderPaymentResource($payment),
            'Payment verified successfully.',
        );
    }
}
