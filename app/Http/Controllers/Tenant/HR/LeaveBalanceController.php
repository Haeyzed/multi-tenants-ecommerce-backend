<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\HR\LeaveBalanceResource;
use App\Models\HR\Employee;
use App\Services\Tenant\HR\LeaveRequestService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Employee leave balances.
 */
#[Group('HR')]
class LeaveBalanceController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService) {}

    #[Response(status: 200, description: 'Employee leave balances.', type: 'array{success: true, message: string, data: LeaveBalanceResource[], meta: null, errors: null}')]
    public function index(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(
            LeaveBalanceResource::collection($this->leaveRequestService->balancesFor($employee)),
            'Leave balances retrieved successfully.',
        );
    }
}
