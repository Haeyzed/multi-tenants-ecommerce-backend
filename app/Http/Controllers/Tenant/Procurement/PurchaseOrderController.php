<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Procurement\ReceiveGoodsRequest;
use App\Http\Requests\Tenant\Procurement\StorePurchaseOrderRequest;
use App\Http\Resources\Tenant\Procurement\GoodsReceiptResource;
use App\Http\Resources\Tenant\Procurement\PurchaseOrderResource;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\User;
use App\Services\Tenant\Procurement\GoodsReceiptService;
use App\Services\Tenant\Procurement\PurchaseOrderService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin purchase order and goods receipt endpoints.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {}

    #[Response(status: 200, description: 'Paginated purchase orders.', type: 'array{success: true, message: string, data: PurchaseOrderResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = $this->purchaseOrderService->list($request->only(['status', 'supplier_id', 'per_page']));

        return $this->success(
            PurchaseOrderResource::collection($orders->items()),
            'Purchase orders retrieved successfully.',
            $this->paginationMeta($orders),
        );
    }

    #[Response(status: 201, description: 'Created purchase order.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        return $this->created(
            new PurchaseOrderResource($this->purchaseOrderService->create($request->validated())),
            'Purchase order created successfully.',
        );
    }

    #[Response(status: 200, description: 'A purchase order.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return $this->success(
            new PurchaseOrderResource($this->purchaseOrderService->show($purchaseOrder)),
            'Purchase order retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Approved purchase order.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        return $this->updated(
            new PurchaseOrderResource($this->purchaseOrderService->approve($purchaseOrder)),
            'Purchase order approved successfully.',
        );
    }

    #[Response(status: 200, description: 'Marked purchase order as ordered.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function markOrdered(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        return $this->updated(
            new PurchaseOrderResource($this->purchaseOrderService->markOrdered($purchaseOrder)),
            'Purchase order marked as ordered.',
        );
    }

    #[Response(status: 201, description: 'Goods receipt created.', type: 'array{success: true, message: string, data: GoodsReceiptResource, meta: null, errors: null}')]
    public function receive(ReceiveGoodsRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('receive', $purchaseOrder);

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();
        $data = $request->validated();

        $receipt = $this->goodsReceiptService->receive(
            $purchaseOrder,
            $data['items'],
            $user,
            $data['notes'] ?? null,
        );

        return $this->created(
            new GoodsReceiptResource($receipt->load('items')),
            'Goods received successfully.',
        );
    }
}
