<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\CheckoutRequest;
use App\Http\Resources\Tenant\Commerce\OrderResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Commerce\CheckoutService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer checkout endpoint.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    /**
     * Convert the active cart into an order.
     */
    #[Response(
        status: 201,
        description: 'Created order from cart.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function store(CheckoutRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $order = $this->checkoutService->checkout($customer, $request->validated());

        return $this->created(
            new OrderResource($order),
            'Checkout completed successfully.',
        );
    }
}
