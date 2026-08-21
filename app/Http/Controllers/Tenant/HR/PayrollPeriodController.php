<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\HR\PayrollPeriodResource;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\PayrollRun;
use App\Services\Tenant\HR\PayrollRunService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Current and historical payroll periods derived from HR settings.
 */
#[Group('HR')]
class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollRunService $payrollRunService) {}

    #[Response(status: 200, description: 'Payroll periods.', type: 'array{success: true, message: string, data: PayrollPeriodResource[], meta: null, errors: null}')]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $periods = PayrollPeriod::query()
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        return $this->success(
            PayrollPeriodResource::collection($periods),
            'Payroll periods retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Current payroll period.', type: 'array{success: true, message: string, data: PayrollPeriodResource, meta: null, errors: null}')]
    public function current(): JsonResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        return $this->success(
            new PayrollPeriodResource($this->payrollRunService->ensureCurrentPeriod()),
            'Current payroll period retrieved successfully.',
        );
    }
}
