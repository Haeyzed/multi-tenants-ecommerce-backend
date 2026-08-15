<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StoreRefundRequest;
use App\Http\Resources\Tenant\Commerce\RefundResource;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Refund;
use App\Services\Tenant\Commerce\RefundService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff refund management endpoints.
 */
class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refundService) {}

    #[Response(status: 200, description: 'Paginated refunds.', type: 'array{success: true, message: string, data: RefundResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Refund::class);

        $refunds = $this->refundService->list($request->only(['order_id', 'status', 'per_page']));

        return $this->success(
            RefundResource::collection($refunds->items()),
            'Refunds retrieved successfully.',
            $this->paginationMeta($refunds),
        );
    }

    #[Response(status: 201, description: 'Created refund.', type: 'array{success: true, message: string, data: RefundResource, meta: null, errors: null}')]
    public function store(StoreRefundRequest $request, Order $order): JsonResponse
    {
        $this->authorize('create', Refund::class);

        /** @var OrderPayment $payment */
        $payment = OrderPayment::query()->findOrFail((int) $request->validated('order_payment_id'));

        return $this->created(
            new RefundResource($this->refundService->create($order, $payment, $request->validated())),
            'Refund processed successfully.',
        );
    }

    #[Response(status: 200, description: 'A refund.', type: 'array{success: true, message: string, data: RefundResource, meta: null, errors: null}')]
    public function show(Refund $refund): JsonResponse
    {
        $this->authorize('view', $refund);

        return $this->success(
            new RefundResource($this->refundService->show($refund)),
            'Refund retrieved successfully.',
        );
    }
}
