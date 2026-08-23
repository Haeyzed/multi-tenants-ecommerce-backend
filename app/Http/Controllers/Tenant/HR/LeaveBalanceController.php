<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\HR\LeaveBalanceResource;
use App\Models\Tenant\HR\Employee;
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
    /**
     * Create a new class instance.
     *
     * @param  LeaveRequestService  $leaveRequestService
     */
    public function __construct(private readonly LeaveRequestService $leaveRequestService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Employee  $employee
     * @return JsonResponse
     */
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
