<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Loyalty\IndexLoyaltyTransactionRequest;
use App\Http\Requests\Tenant\Loyalty\PreviewLoyaltyRedemptionRequest;
use App\Http\Resources\Tenant\Loyalty\LoyaltyAccountResource;
use App\Http\Resources\Tenant\Loyalty\LoyaltyTransactionResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Loyalty\LoyaltyService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-facing loyalty balance, ledger and redemption preview.
 */
class CustomerLoyaltyController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CartService  $cartService
     * @param  LoyaltyService  $loyalty
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * Get the authenticated customer's loyalty account.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Customer loyalty account.', type: 'array{success: true, message: string, data: LoyaltyAccountResource, meta: null, errors: null}')]
    public function account(): JsonResponse
    {
        return $this->success(
            new LoyaltyAccountResource($this->loyalty->getOrCreateAccount($this->customer())),
            'Loyalty account retrieved successfully.',
        );
    }

    /**
     * List the authenticated customer's point movements.
     *
     * @param  IndexLoyaltyTransactionRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated loyalty ledger entries.', type: 'array{success: true, message: string, data: LoyaltyTransactionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function transactions(IndexLoyaltyTransactionRequest $request): JsonResponse
    {
        $account = $this->loyalty->getOrCreateAccount($this->customer());
        $transactions = $this->loyalty->listTransactions($account, $request->validated());

        return $this->success(
            LoyaltyTransactionResource::collection($transactions->items()),
            'Loyalty transactions retrieved successfully.',
            $this->paginationMeta($transactions),
        );
    }

    /**
     * Preview what a point redemption would be worth against the active cart.
     *
     * @param  PreviewLoyaltyRedemptionRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Redemption preview.', type: 'array{success: true, message: string, data: array{points: int, money_value: string}, meta: null, errors: null}')]
    public function previewRedemption(PreviewLoyaltyRedemptionRequest $request): JsonResponse
    {
        $customer = $this->customer();
        $cart = $this->cartService->getCart($customer);
        $totals = $this->cartService->totals($cart);

        $result = $this->loyalty->previewRedemption(
            $customer,
            (int) $request->validated()['points'],
            (string) $totals['subtotal'],
        );

        return $this->success(
            ['points' => $result->points, 'money_value' => $result->moneyValue],
            'Loyalty redemption preview generated successfully.',
        );
    }

    /**
     * Customer.
     *
     * @return Customer
     */
    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }
}
