<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\HR\EmploymentRecordResource;
use App\Models\HR\Employee;
use App\Services\Tenant\HR\EmployeeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Employee employment history snapshots.
 */
#[Group('HR')]
class EmploymentRecordController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    #[Response(status: 200, description: 'Employment history.', type: 'array{success: true, message: string, data: EmploymentRecordResource[], meta: null, errors: null}')]
    public function index(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(
            EmploymentRecordResource::collection($this->employeeService->employmentHistory($employee)),
            'Employment history retrieved successfully.',
        );
    }
}
