<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\CreatePosSaleRequest;
use App\Http\Requests\Tenant\Pos\RefundPosSaleRequest;
use App\Http\Resources\Tenant\Commerce\OrderResource;
use App\Models\Tenant\Order;
use App\Models\Tenant\PosSession;
use App\Services\Tenant\Pos\PosSaleService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * POS sales and refunds.
 */
class PosSaleController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PosSaleService  $sales
     */
    public function __construct(private readonly PosSaleService $sales) {}

    /**
     * Create a resource.
     *
     * @param  CreatePosSaleRequest  $request
     * @param  PosSession  $pos_session
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'POS sale created.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function store(CreatePosSaleRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->authorize('sell', $pos_session);

        $order = $this->sales->createSale($pos_session, $request->validated());

        return $this->created(
            new OrderResource($order),
            'POS sale created successfully.',
        );
    }

    /**
     * Refund.
     *
     * @param  RefundPosSaleRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'POS refund created.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function refund(RefundPosSaleRequest $request, Order $order): JsonResponse
    {
        abort_unless($request->user()?->can('pos.refund'), 403);

        $refund = $this->sales->refund($order, $request->validated());

        return $this->created(
            [
                'id' => $refund->id,
                'order_id' => $refund->order_id,
                'amount' => $refund->amount,
                'status' => $refund->status?->value,
                'reference' => $refund->reference,
            ],
            'POS refund created successfully.',
        );
    }
}
