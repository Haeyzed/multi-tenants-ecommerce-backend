<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\IndexSellerPayoutRequest;
use App\Http\Requests\Tenant\Marketplace\StoreSellerPayoutRequest;
use App\Http\Resources\Tenant\Marketplace\SellerPayoutResource;
use App\Models\Tenant\SellerPayout;
use App\Services\Tenant\Marketplace\SellerPayoutService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Seller payout batching and listing.
 */
class SellerPayoutController extends Controller
{
    public function __construct(private readonly SellerPayoutService $payouts) {}

    #[Response(status: 200, description: 'Paginated payouts.', type: 'array{success: true, message: string, data: SellerPayoutResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexSellerPayoutRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SellerPayout::class);

        $payouts = $this->payouts->list($request->validated(), $request->user());

        return $this->success(
            SellerPayoutResource::collection($payouts->items()),
            'Payouts retrieved successfully.',
            $this->paginationMeta($payouts),
        );
    }

    #[Response(status: 201, description: 'Created payout.', type: 'array{success: true, message: string, data: SellerPayoutResource, meta: null, errors: null}')]
    public function store(StoreSellerPayoutRequest $request): JsonResponse
    {
        $this->authorize('create', SellerPayout::class);

        return $this->created(
            new SellerPayoutResource($this->payouts->create($request->validated(), $request->user())),
            'Payout created successfully.',
        );
    }

    #[Response(status: 200, description: 'A payout.', type: 'array{success: true, message: string, data: SellerPayoutResource, meta: null, errors: null}')]
    public function show(SellerPayout $payout): JsonResponse
    {
        $this->authorize('view', $payout);

        return $this->success(
            new SellerPayoutResource($this->payouts->show($payout)),
            'Payout retrieved successfully.',
        );
    }
}
