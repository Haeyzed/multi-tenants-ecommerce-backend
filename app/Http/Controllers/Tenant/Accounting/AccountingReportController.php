<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Accounting\JournalEntryLineResource;
use App\Models\Tenant\Account;
use App\Services\Tenant\Accounting\AccountingReportService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trial balance, ledger, and account balance reporting.
 */
class AccountingReportController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports) {}

    #[Response(
        status: 200,
        description: 'Trial balance rows for active accounts.',
        type: 'array{success: true, message: string, data: array<int, array{code: string, name: string, type: string, debit: string, credit: string, balance: string}>, meta: null, errors: null}',
    )]
    public function trialBalance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $asOf = $request->query('as_of');
        $asOfDate = is_string($asOf) && $asOf !== '' ? $asOf : null;

        return $this->success(
            $this->reports->trialBalance($asOfDate),
            'Trial balance retrieved successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Paginated ledger lines for an account.',
        type: 'array{success: true, message: string, data: JournalEntryLineResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function ledger(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $lines = $this->reports->ledger($account, $request->only(['date_from', 'date_to', 'per_page']));

        return $this->success(
            JournalEntryLineResource::collection($lines->items()),
            'Account ledger retrieved successfully.',
            $this->paginationMeta($lines),
        );
    }

    #[Response(
        status: 200,
        description: 'Account debit/credit totals and net balance.',
        type: 'array{success: true, message: string, data: array{debit_total: string, credit_total: string, balance: string}, meta: null, errors: null}',
    )]
    public function balance(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $asOf = $request->query('as_of');
        $asOfDate = is_string($asOf) && $asOf !== '' ? $asOf : null;

        return $this->success(
            $this->reports->accountBalance($account, $asOfDate),
            'Account balance retrieved successfully.',
        );
    }
}
