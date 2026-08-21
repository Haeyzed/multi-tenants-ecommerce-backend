<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Loyalty\IndexLoyaltyAccountRequest;
use App\Http\Requests\Tenant\Loyalty\IndexLoyaltyTransactionRequest;
use App\Http\Requests\Tenant\Loyalty\StoreLoyaltyAdjustmentRequest;
use App\Http\Resources\Tenant\Loyalty\LoyaltyAccountResource;
use App\Http\Resources\Tenant\Loyalty\LoyaltyTransactionResource;
use App\Models\Tenant\LoyaltyAccount;
use App\Services\Tenant\Loyalty\LoyaltyService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Staff administration of customer loyalty accounts.
 */
class LoyaltyAccountController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  LoyaltyService  $loyalty
     */
    public function __construct(private readonly LoyaltyService $loyalty) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexLoyaltyAccountRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated loyalty accounts.', type: 'array{success: true, message: string, data: LoyaltyAccountResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexLoyaltyAccountRequest $request): JsonResponse
    {
        $this->authorize('viewAny', LoyaltyAccount::class);

        $accounts = $this->loyalty->listAccounts($request->validated());

        return $this->success(
            LoyaltyAccountResource::collection($accounts->items()),
            'Loyalty accounts retrieved successfully.',
            $this->paginationMeta($accounts),
        );
    }

    /**
     * Transactions.
     *
     * @param  IndexLoyaltyTransactionRequest  $request
     * @param  LoyaltyAccount  $loyalty_account
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated loyalty ledger entries.', type: 'array{success: true, message: string, data: LoyaltyTransactionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function transactions(IndexLoyaltyTransactionRequest $request, LoyaltyAccount $loyalty_account): JsonResponse
    {
        $this->authorize('view', $loyalty_account);

        $transactions = $this->loyalty->listTransactions($loyalty_account, $request->validated());

        return $this->success(
            LoyaltyTransactionResource::collection($transactions->items()),
            'Loyalty transactions retrieved successfully.',
            $this->paginationMeta($transactions),
        );
    }

    /**
     * Store adjustment.
     *
     * @param  StoreLoyaltyAdjustmentRequest  $request
     * @param  LoyaltyAccount  $loyalty_account
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Recorded adjustment.', type: 'array{success: true, message: string, data: LoyaltyTransactionResource, meta: null, errors: null}')]
    public function storeAdjustment(StoreLoyaltyAdjustmentRequest $request, LoyaltyAccount $loyalty_account): JsonResponse
    {
        $this->authorize('adjust', $loyalty_account);

        $validated = $request->validated();

        $transaction = $this->loyalty->adjust(
            $loyalty_account,
            (int) $validated['points'],
            $validated['description'] ?? null,
        );

        return $this->created(
            new LoyaltyTransactionResource($transaction),
            'Loyalty adjustment recorded successfully.',
        );
    }
}
