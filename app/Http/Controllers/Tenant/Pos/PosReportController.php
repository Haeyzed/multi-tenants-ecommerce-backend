<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\PosReportRequest;
use App\Models\Tenant\PosSession;
use App\Services\Tenant\Pos\PosReportService;
use App\Services\Tenant\Pos\PosSessionService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * POS reporting aggregates.
 */
class PosReportController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PosReportService  $reports
     * @param  PosSessionService  $sessions
     */
    public function __construct(
        private readonly PosReportService $reports,
        private readonly PosSessionService $sessions,
    ) {}

    /**
     * Session summary.
     *
     * @param  PosSession  $pos_session
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Session summary.', type: 'array{success: true, message: string, data: array, meta: null, errors: null}')]
    public function sessionSummary(PosSession $pos_session): JsonResponse
    {
        abort_unless(request()->user()?->can('pos.reports.view') || request()->user()?->can('pos.view'), 403);

        return $this->success(
            $this->reports->sessionSummary($pos_session, $this->sessions),
            'POS session summary retrieved successfully.',
        );
    }

    /**
     * Sales by terminal.
     *
     * @param  PosReportRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Sales by terminal.', type: 'array{success: true, message: string, data: array, meta: null, errors: null}')]
    public function salesByTerminal(PosReportRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.reports.view'), 403);

        return $this->success(
            $this->reports->salesByTerminal($request->validated()),
            'POS sales by terminal retrieved successfully.',
        );
    }

    /**
     * Sales by cashier.
     *
     * @param  PosReportRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Sales by cashier.', type: 'array{success: true, message: string, data: array, meta: null, errors: null}')]
    public function salesByCashier(PosReportRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.reports.view'), 403);

        return $this->success(
            $this->reports->salesByCashier($request->validated()),
            'POS sales by cashier retrieved successfully.',
        );
    }

    /**
     * Payment method totals.
     *
     * @param  PosReportRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Payment method totals.', type: 'array{success: true, message: string, data: array, meta: null, errors: null}')]
    public function paymentMethodTotals(PosReportRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('pos.reports.view'), 403);

        return $this->success(
            $this->reports->paymentMethodTotals($request->validated()),
            'POS payment method totals retrieved successfully.',
        );
    }
}
