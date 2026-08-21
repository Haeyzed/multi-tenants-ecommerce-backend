<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StoreOrderReturnRequest;
use App\Http\Resources\Tenant\Commerce\OrderReturnResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderReturn;
use App\Services\Tenant\Commerce\OrderReturnService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Customer return request endpoints.
 */
class CustomerOrderReturnController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  OrderReturnService  $returns
     */
    public function __construct(private readonly OrderReturnService $returns) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Customer returns.', type: 'array{success: true, message: string, data: OrderReturnResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $returns = $this->returns->listForCustomer($customer, $request->only(['status', 'per_page']));

        return $this->success(
            OrderReturnResource::collection($returns->items()),
            'Returns retrieved successfully.',
            $this->paginationMeta($returns),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreOrderReturnRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function store(StoreOrderReturnRequest $request, Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->created(
            new OrderReturnResource($this->returns->request($customer, $order, $request->validated())),
            'Return requested successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function show(OrderReturn $order_return): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ((int) $order_return->customer_id !== (int) $customer->id) {
            throw new AccessDeniedHttpException('Return does not belong to this customer.');
        }

        return $this->success(
            new OrderReturnResource($this->returns->show($order_return)),
            'Return retrieved successfully.',
        );
    }
}
