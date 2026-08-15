<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\AddCartItemRequest;
use App\Http\Requests\Tenant\Commerce\UpdateCartItemRequest;
use App\Http\Resources\Tenant\Commerce\CartItemResource;
use App\Http\Resources\Tenant\Commerce\CartResource;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Commerce\CartService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer shopping cart endpoints.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * Get the authenticated customer's active cart.
     */
    #[Response(
        status: 200,
        description: 'Active cart with items and totals.',
        type: 'array{success: true, message: string, data: CartResource, meta: null, errors: null}',
    )]
    public function show(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new CartResource($this->cartService->getCart($customer)),
            'Cart retrieved successfully.',
        );
    }

    /**
     * Clear all items from the active cart.
     */
    #[Response(
        status: 200,
        description: 'Cart cleared.',
        type: 'array{success: true, message: string, data: CartResource, meta: null, errors: null}',
    )]
    public function destroy(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->cartService->clear($customer);

        return $this->success(
            new CartResource($this->cartService->getCart($customer)),
            'Cart cleared successfully.',
        );
    }

    /**
     * Add an item to the cart (unit price resolved server-side).
     */
    #[Response(
        status: 201,
        description: 'Created or updated cart item.',
        type: 'array{success: true, message: string, data: CartItemResource, meta: null, errors: null}',
    )]
    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $validated = $request->validated();

        $item = $this->cartService->addItem(
            $customer,
            (int) ($validated['product_id'] ?? 0),
            isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null,
            (int) $validated['quantity'],
            isset($validated['seller_offer_id']) ? (int) $validated['seller_offer_id'] : null,
        );

        return $this->created(
            new CartItemResource($item),
            'Cart item added successfully.',
        );
    }

    /**
     * Update a cart item quantity.
     */
    #[Response(
        status: 200,
        description: 'Updated cart item.',
        type: 'array{success: true, message: string, data: CartItemResource, meta: null, errors: null}',
    )]
    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $item = $this->cartService->updateItem(
            $customer,
            $item,
            (int) $request->validated('quantity'),
        );

        return $this->updated(
            new CartItemResource($item),
            'Cart item updated successfully.',
        );
    }

    /**
     * Remove a cart item.
     */
    #[Response(
        status: 200,
        description: 'Cart item removed.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyItem(CartItem $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->cartService->removeItem($customer, $item);

        return $this->deleted('Cart item removed successfully.');
    }
}
