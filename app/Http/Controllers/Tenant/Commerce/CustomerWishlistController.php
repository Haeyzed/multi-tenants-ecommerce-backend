<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\CheckWishlistProductRequest;
use App\Http\Requests\Tenant\Commerce\StoreWishlistItemRequest;
use App\Http\Resources\Tenant\Commerce\WishlistItemResource;
use App\Http\Resources\Tenant\Commerce\WishlistResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\WishlistItem;
use App\Services\Tenant\Commerce\WishlistService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer wishlist endpoints (auth customer only).
 */
class CustomerWishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlistService) {}

    /**
     * Get the authenticated customer's wishlist.
     */
    #[Response(
        status: 200,
        description: 'Customer wishlist with items.',
        type: 'array{success: true, message: string, data: WishlistResource, meta: null, errors: null}',
    )]
    public function show(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new WishlistResource($this->wishlistService->getWishlist($customer)),
            'Wishlist retrieved successfully.',
        );
    }

    /**
     * Add a product to the wishlist.
     */
    #[Response(
        status: 201,
        description: 'Wishlist item created.',
        type: 'array{success: true, message: string, data: WishlistItemResource, meta: null, errors: null}',
    )]
    public function storeItem(StoreWishlistItemRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $validated = $request->validated();

        $item = $this->wishlistService->addItem(
            $customer,
            (int) $validated['product_id'],
            isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null,
        );

        return $this->created(
            new WishlistItemResource($item),
            'Wishlist item added successfully.',
        );
    }

    /**
     * Remove an item from the wishlist.
     */
    #[Response(
        status: 200,
        description: 'Wishlist item removed.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyItem(WishlistItem $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->wishlistService->removeItem($customer, $item);

        return $this->deleted('Wishlist item removed successfully.');
    }

    /**
     * Check whether a product is in the customer's wishlist.
     */
    #[Response(
        status: 200,
        description: 'Wishlist membership check.',
        type: 'array{success: true, message: string, data: array{in_wishlist: bool, wishlist_item_id: int|null}, meta: null, errors: null}',
    )]
    public function check(CheckWishlistProductRequest $request, Product $product): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $validated = $request->validated();

        return $this->success(
            $this->wishlistService->check(
                $customer,
                $product,
                isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null,
            ),
            'Wishlist check completed.',
        );
    }
}
