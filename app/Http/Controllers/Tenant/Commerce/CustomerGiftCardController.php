<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\PreviewGiftCardRequest;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\GiftCardService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-facing gift card balance preview at the cart.
 */
class CustomerGiftCardController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly GiftCardService $giftCardService,
    ) {}

    /**
     * Preview how much of the current cart a gift card could cover.
     *
     * The preview is indicative only: it uses the cart subtotal, while the amount
     * actually redeemed is recalculated against the final order total at checkout.
     */
    #[Response(status: 200, description: 'Gift card preview for the active cart.', type: 'array{success: true, message: string, data: array{last_four: string, currency: string, balance: string, applicable_amount: string, expires_at: string|null}, meta: null, errors: null}')]
    public function preview(PreviewGiftCardRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $cart = $this->cartService->getCart($customer);
        $totals = $this->cartService->totals($cart);

        $preview = $this->giftCardService->preview(
            (string) $request->validated()['gift_card_code'],
            $cart->currency,
            $totals['grand_total'],
        );

        return $this->success($preview, 'Gift card preview retrieved successfully.');
    }
}
