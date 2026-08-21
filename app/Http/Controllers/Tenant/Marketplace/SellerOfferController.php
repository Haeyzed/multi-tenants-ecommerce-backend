<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\StoreSellerOfferRequest;
use App\Http\Requests\Tenant\Marketplace\UpdateSellerOfferRequest;
use App\Http\Resources\Tenant\Marketplace\SellerOfferResource;
use App\Models\Tenant\SellerOffer;
use App\Services\Tenant\Marketplace\SellerOfferService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace seller offer administration.
 */
class SellerOfferController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  SellerOfferService  $sellerOfferService
     */
    public function __construct(private readonly SellerOfferService $sellerOfferService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated offers.', type: 'array{success: true, message: string, data: SellerOfferResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SellerOffer::class);

        /** @var Authenticatable $actor */
        $actor = $request->user();

        $offers = $this->sellerOfferService->list(
            $request->only(['seller_id', 'product_id', 'status', 'search', 'per_page']),
            $actor,
        );

        return $this->success(
            SellerOfferResource::collection($offers->items()),
            'Seller offers retrieved successfully.',
            $this->paginationMeta($offers),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreSellerOfferRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created offer.', type: 'array{success: true, message: string, data: SellerOfferResource, meta: null, errors: null}')]
    public function store(StoreSellerOfferRequest $request): JsonResponse
    {
        $this->authorize('create', SellerOffer::class);

        /** @var Authenticatable $actor */
        $actor = $request->user();

        return $this->created(
            new SellerOfferResource($this->sellerOfferService->store($request->validated(), $actor)),
            'Seller offer created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  SellerOffer  $seller_offer
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'An offer.', type: 'array{success: true, message: string, data: SellerOfferResource, meta: null, errors: null}')]
    public function show(SellerOffer $seller_offer): JsonResponse
    {
        $this->authorize('view', $seller_offer);

        return $this->success(
            new SellerOfferResource($this->sellerOfferService->show($seller_offer)),
            'Seller offer retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateSellerOfferRequest  $request
     * @param  SellerOffer  $seller_offer
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated offer.', type: 'array{success: true, message: string, data: SellerOfferResource, meta: null, errors: null}')]
    public function update(UpdateSellerOfferRequest $request, SellerOffer $seller_offer): JsonResponse
    {
        $this->authorize('update', $seller_offer);

        /** @var Authenticatable $actor */
        $actor = $request->user();

        return $this->updated(
            new SellerOfferResource($this->sellerOfferService->update($seller_offer, $request->validated(), $actor)),
            'Seller offer updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Request  $request
     * @param  SellerOffer  $seller_offer
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted offer.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Request $request, SellerOffer $seller_offer): JsonResponse
    {
        $this->authorize('delete', $seller_offer);

        /** @var Authenticatable $actor */
        $actor = $request->user();
        $this->sellerOfferService->destroy($seller_offer, $actor);

        return $this->deleted('Seller offer deleted successfully.');
    }
}
