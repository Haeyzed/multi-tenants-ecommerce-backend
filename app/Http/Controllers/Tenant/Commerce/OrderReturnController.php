<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\InspectReturnItemRequest;
use App\Http\Resources\Tenant\Commerce\OrderReturnItemResource;
use App\Http\Resources\Tenant\Commerce\OrderReturnResource;
use App\Models\Tenant\OrderReturn;
use App\Models\Tenant\OrderReturnItem;
use App\Models\Tenant\User;
use App\Services\Tenant\Commerce\OrderReturnService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff / seller return merchandise administration.
 */
class OrderReturnController extends Controller
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
    #[Response(status: 200, description: 'Paginated returns.', type: 'array{success: true, message: string, data: OrderReturnResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderReturn::class);

        /** @var User $user */
        $user = $request->user();
        $returns = $this->returns->list($request->only(['status', 'order_id', 'seller_id', 'per_page']), $user);

        return $this->success(
            OrderReturnResource::collection($returns->items()),
            'Returns retrieved successfully.',
            $this->paginationMeta($returns),
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
        $this->authorize('view', $order_return);

        return $this->success(
            new OrderReturnResource($this->returns->show($order_return)),
            'Return retrieved successfully.',
        );
    }

    /**
     * Approve.
     *
     * @param  Request  $request
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Approved return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function approve(Request $request, OrderReturn $order_return): JsonResponse
    {
        $this->authorize('approve', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->approve($order_return, $request->input('admin_note'))),
            'Return approved successfully.',
        );
    }

    /**
     * Reject.
     *
     * @param  Request  $request
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Rejected return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function reject(Request $request, OrderReturn $order_return): JsonResponse
    {
        $this->authorize('reject', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->reject($order_return, $request->input('admin_note'))),
            'Return rejected successfully.',
        );
    }

    /**
     * Mark received.
     *
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Received return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function markReceived(OrderReturn $order_return): JsonResponse
    {
        $this->authorize('inspect', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->markReceived($order_return)),
            'Return marked as received.',
        );
    }

    /**
     * Start inspection.
     *
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Inspecting return.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function startInspection(OrderReturn $order_return): JsonResponse
    {
        $this->authorize('inspect', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->startInspection($order_return)),
            'Return inspection started.',
        );
    }

    /**
     * Inspect item.
     *
     * @param  InspectReturnItemRequest  $request
     * @param  OrderReturnItem  $order_return_item
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Inspected item.', type: 'array{success: true, message: string, data: OrderReturnItemResource, meta: null, errors: null}')]
    public function inspectItem(InspectReturnItemRequest $request, OrderReturnItem $order_return_item): JsonResponse
    {
        $order_return_item->loadMissing('orderReturn');
        $this->authorize('inspect', $order_return_item->orderReturn);

        /** @var User $user */
        $user = $request->user();

        return $this->updated(
            new OrderReturnItemResource($this->returns->inspectItem($order_return_item, $user, $request->validated())),
            'Return item inspected successfully.',
        );
    }

    /**
     * Approve for refund.
     *
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Approved for refund.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function approveForRefund(OrderReturn $order_return): JsonResponse
    {
        $this->authorize('complete', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->approveForRefund($order_return)),
            'Return approved for refund.',
        );
    }

    /**
     * Process refund.
     *
     * @param  OrderReturn  $order_return
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Refund processed.', type: 'array{success: true, message: string, data: OrderReturnResource, meta: null, errors: null}')]
    public function processRefund(OrderReturn $order_return): JsonResponse
    {
        $this->authorize('complete', $order_return);

        return $this->updated(
            new OrderReturnResource($this->returns->processRefund($order_return)),
            'Return refund processed successfully.',
        );
    }
}
