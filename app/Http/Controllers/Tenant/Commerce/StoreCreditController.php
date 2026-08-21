<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StoreCreditTransactionRequest;
use App\Http\Resources\Tenant\Commerce\StoreCreditAccountResource;
use App\Http\Resources\Tenant\Commerce\StoreCreditTransactionResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\StoreCreditAccount;
use App\Services\Tenant\Commerce\StoreCreditService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff administration of customer store credit wallets.
 */
class StoreCreditController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StoreCreditService  $storeCreditService
     */
    public function __construct(private readonly StoreCreditService $storeCreditService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated store credit accounts.', type: 'array{success: true, message: string, data: StoreCreditAccountResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreCreditAccount::class);

        $accounts = $this->storeCreditService->listAccounts($request->only(['status', 'customer_id', 'per_page']));

        return $this->success(
            StoreCreditAccountResource::collection($accounts->items()),
            'Store credit accounts retrieved successfully.',
            $this->paginationMeta($accounts),
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Customer  $customer
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A customer store credit account.', type: 'array{success: true, message: string, data: StoreCreditAccountResource, meta: null, errors: null}')]
    public function show(Customer $customer): JsonResponse
    {
        $account = $this->storeCreditService->getOrCreateAccount($customer);
        $this->authorize('view', $account);

        return $this->success(
            new StoreCreditAccountResource($account),
            'Store credit account retrieved successfully.',
        );
    }

    /**
     * Transactions.
     *
     * @param  Request  $request
     * @param  Customer  $customer
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated store credit ledger.', type: 'array{success: true, message: string, data: StoreCreditTransactionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function transactions(Request $request, Customer $customer): JsonResponse
    {
        $account = $this->storeCreditService->getOrCreateAccount($customer);
        $this->authorize('view', $account);

        $transactions = $this->storeCreditService->transactions($customer, $request->only(['type', 'per_page']));

        return $this->success(
            StoreCreditTransactionResource::collection($transactions->items()),
            'Store credit transactions retrieved successfully.',
            $this->paginationMeta($transactions),
        );
    }

    /**
     * Credit.
     *
     * @param  StoreCreditTransactionRequest  $request
     * @param  Customer  $customer
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Recorded store credit.', type: 'array{success: true, message: string, data: StoreCreditTransactionResource, meta: null, errors: null}')]
    public function credit(StoreCreditTransactionRequest $request, Customer $customer): JsonResponse
    {
        $account = $this->storeCreditService->getOrCreateAccount($customer);
        $this->authorize('manage', $account);

        $data = $request->validated();

        $transaction = $this->storeCreditService->credit(
            $customer,
            (string) $data['amount'],
            StoreCreditTransactionType::Credit,
            $data['description'] ?? null,
        );

        return $this->created(
            new StoreCreditTransactionResource($transaction),
            'Store credit added successfully.',
        );
    }

    /**
     * Debit.
     *
     * @param  StoreCreditTransactionRequest  $request
     * @param  Customer  $customer
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Recorded store credit debit.', type: 'array{success: true, message: string, data: StoreCreditTransactionResource, meta: null, errors: null}')]
    public function debit(StoreCreditTransactionRequest $request, Customer $customer): JsonResponse
    {
        $account = $this->storeCreditService->getOrCreateAccount($customer);
        $this->authorize('manage', $account);

        $data = $request->validated();

        $transaction = $this->storeCreditService->debit(
            $customer,
            (string) $data['amount'],
            $data['description'] ?? null,
        );

        return $this->created(
            new StoreCreditTransactionResource($transaction),
            'Store credit debited successfully.',
        );
    }
}
