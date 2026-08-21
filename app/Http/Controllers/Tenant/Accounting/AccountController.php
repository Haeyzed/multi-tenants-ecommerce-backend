<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Accounting\AccountResource;
use App\Models\Tenant\Account;
use App\Services\Tenant\Accounting\AccountService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chart of accounts listing.
 */
class AccountController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  AccountService  $accountService
     */
    public function __construct(private readonly AccountService $accountService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated accounts.',
        type: 'array{success: true, message: string, data: AccountResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $accounts = $this->accountService->list($request->only(['type', 'is_active', 'search', 'per_page']));

        return $this->success(
            AccountResource::collection($accounts->items()),
            'Accounts retrieved successfully.',
            $this->paginationMeta($accounts),
        );
    }
}
