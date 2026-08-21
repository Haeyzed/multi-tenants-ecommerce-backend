<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Commerce\StoreCreditAccountResource;
use App\Http\Resources\Tenant\Commerce\StoreCreditTransactionResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Commerce\StoreCreditService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer access to their own store credit wallet.
 */
class CustomerStoreCreditController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StoreCreditService  $storeCreditService
     */
    public function __construct(private readonly StoreCreditService $storeCreditService) {}

    /**
     * Retrieve a single resource.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'The authenticated customer store credit account.', type: 'array{success: true, message: string, data: StoreCreditAccountResource, meta: null, errors: null}')]
    public function show(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new StoreCreditAccountResource($this->storeCreditService->getOrCreateAccount($customer)),
            'Store credit balance retrieved successfully.',
        );
    }

    /**
     * Transactions.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated store credit ledger.', type: 'array{success: true, message: string, data: StoreCreditTransactionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function transactions(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $transactions = $this->storeCreditService->transactions($customer, $request->only(['type', 'per_page']));

        return $this->success(
            StoreCreditTransactionResource::collection($transactions->items()),
            'Store credit transactions retrieved successfully.',
            $this->paginationMeta($transactions),
        );
    }
}
