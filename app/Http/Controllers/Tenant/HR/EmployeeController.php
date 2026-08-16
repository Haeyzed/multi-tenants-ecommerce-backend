<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexEmployeeRequest;
use App\Http\Requests\Tenant\HR\StoreEmployeeRequest;
use App\Http\Requests\Tenant\HR\UpdateEmployeeRequest;
use App\Http\Resources\Tenant\HR\EmployeeResource;
use App\Models\Tenant\Employee;
use App\Services\Tenant\HR\EmployeeService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant HR employee endpoints.
 */
class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    #[Response(status: 200, description: 'Paginated employees.', type: 'array{success: true, message: string, data: EmployeeResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexEmployeeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->employeeService->list($request->validated());

        return $this->success(
            EmployeeResource::collection($employees->items()),
            'Employees retrieved successfully.',
            $this->paginationMeta($employees),
        );
    }

    #[Response(status: 201, description: 'Created employee.', type: 'array{success: true, message: string, data: EmployeeResource, meta: null, errors: null}')]
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        return $this->created(
            new EmployeeResource($this->employeeService->store($request->validated())),
            'Employee created successfully.',
        );
    }

    #[Response(status: 200, description: 'An employee.', type: 'array{success: true, message: string, data: EmployeeResource, meta: null, errors: null}')]
    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(
            new EmployeeResource($this->employeeService->show($employee)),
            'Employee retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated employee.', type: 'array{success: true, message: string, data: EmployeeResource, meta: null, errors: null}')]
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        return $this->updated(
            new EmployeeResource($this->employeeService->update($employee, $request->validated())),
            'Employee updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted employee.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $this->employeeService->destroy($employee);

        return $this->deleted('Employee deleted successfully.');
    }
}
