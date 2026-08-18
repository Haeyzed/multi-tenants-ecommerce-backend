<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\UpsertEmployeeSalaryRequest;
use App\Http\Resources\Tenant\HR\EmployeeSalaryResource;
use App\Http\Resources\Tenant\HR\EmployeeSalaryRevisionResource;
use App\Models\Tenant\Employee;
use App\Services\Tenant\HR\EmployeeSalaryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Employee compensation endpoints.
 */
#[Group('HR')]
class EmployeeSalaryController extends Controller
{
    public function __construct(private readonly EmployeeSalaryService $employeeSalaryService) {}

    #[Response(status: 200, description: 'Employee salary configuration.', type: 'array{success: true, message: string, data: EmployeeSalaryResource|null, meta: null, errors: null}')]
    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('viewSalary', $employee);

        $salary = $this->employeeSalaryService->show($employee);

        return $this->success(
            $salary === null ? null : new EmployeeSalaryResource($salary),
            $salary === null ? 'Employee salary is not configured.' : 'Employee salary retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated employee salary.', type: 'array{success: true, message: string, data: EmployeeSalaryResource, meta: null, errors: null}')]
    public function upsert(UpsertEmployeeSalaryRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('manageSalary', $employee);

        return $this->updated(
            new EmployeeSalaryResource($this->employeeSalaryService->upsert($employee, $request->validated())),
            'Employee salary saved successfully.',
        );
    }

    #[Response(status: 200, description: 'Salary history.', type: 'array{success: true, message: string, data: EmployeeSalaryRevisionResource[], meta: null, errors: null}')]
    public function history(Employee $employee): JsonResponse
    {
        $this->authorize('viewSalary', $employee);

        return $this->success(
            EmployeeSalaryRevisionResource::collection($this->employeeSalaryService->history($employee)),
            'Salary history retrieved successfully.',
        );
    }
}
