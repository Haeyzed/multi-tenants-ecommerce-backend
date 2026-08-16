<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\StoreSellerRequest;
use App\Http\Requests\Tenant\Marketplace\UpdateSellerRequest;
use App\Http\Resources\Tenant\Marketplace\SellerResource;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Marketplace\SellerService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant marketplace administrator seller management.
 */
class SellerController extends Controller
{
    public function __construct(private readonly SellerService $sellerService) {}

    #[Response(status: 200, description: 'Paginated sellers.', type: 'array{success: true, message: string, data: SellerResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Seller::class);

        $sellers = $this->sellerService->list($request->only([
            'search',
            'status',
            'verification_status',
            'per_page',
        ]));

        return $this->success(
            SellerResource::collection($sellers->items()),
            'Sellers retrieved successfully.',
            $this->paginationMeta($sellers),
        );
    }

    #[Response(status: 201, description: 'Created seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function store(StoreSellerRequest $request): JsonResponse
    {
        $this->authorize('create', Seller::class);

        return $this->created(
            new SellerResource($this->sellerService->store($request->validated())),
            'Seller created successfully.',
        );
    }

    #[Response(status: 200, description: 'A seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function show(Seller $seller): JsonResponse
    {
        $this->authorize('view', $seller);

        return $this->success(
            new SellerResource($this->sellerService->show($seller)),
            'Seller retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function update(UpdateSellerRequest $request, Seller $seller): JsonResponse
    {
        $this->authorize('update', $seller);

        $data = $request->validated();
        /** @var Authenticatable|null $actor */
        $actor = $request->user();

        if ($actor instanceof Seller) {
            $data = collect($data)
                ->except(['commission_type', 'commission_rate', 'commission_fixed_amount'])
                ->all();
        }

        return $this->updated(
            new SellerResource($this->sellerService->update($seller, $data)),
            'Seller updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Approved seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function approve(Seller $seller): JsonResponse
    {
        $this->authorize('approve', $seller);

        return $this->updated(
            new SellerResource($this->sellerService->approve($seller)),
            'Seller approved successfully.',
        );
    }

    #[Response(status: 200, description: 'Rejected seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function reject(Seller $seller): JsonResponse
    {
        $this->authorize('reject', $seller);

        return $this->updated(
            new SellerResource($this->sellerService->reject($seller)),
            'Seller rejected successfully.',
        );
    }

    #[Response(status: 200, description: 'Suspended seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function suspend(Seller $seller): JsonResponse
    {
        $this->authorize('suspend', $seller);

        return $this->updated(
            new SellerResource($this->sellerService->suspend($seller)),
            'Seller suspended successfully.',
        );
    }

    #[Response(status: 200, description: 'Activated seller.', type: 'array{success: true, message: string, data: SellerResource, meta: null, errors: null}')]
    public function activate(Seller $seller): JsonResponse
    {
        $this->authorize('activate', $seller);

        return $this->updated(
            new SellerResource($this->sellerService->activate($seller)),
            'Seller activated successfully.',
        );
    }
}
