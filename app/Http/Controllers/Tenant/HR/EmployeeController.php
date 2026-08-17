<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexEmployeeRequest;
use App\Http\Requests\Tenant\HR\StoreEmployeeDocumentRequest;
use App\Http\Requests\Tenant\HR\StoreEmployeeRequest;
use App\Http\Requests\Tenant\HR\UpdateEmployeeRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\HR\EmployeeResource;
use App\Models\Tenant\Employee;
use App\Services\Tenant\HR\EmployeeService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    #[Response(status: 200, description: 'Employee documents.', type: 'array{success: true, message: string, data: MediaResource[], meta: null, errors: null}')]
    public function documents(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(
            MediaResource::collection($employee->getMedia('documents')),
            'Employee documents retrieved successfully.',
        );
    }

    #[Response(status: 201, description: 'Uploaded employee document.', type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}')]
    public function storeDocument(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $media = $this->employeeService->addDocument($employee, $file, [
            'name' => $request->validated('name'),
        ]);

        return $this->created(
            new MediaResource($media),
            'Employee document uploaded successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted employee document.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroyDocument(Employee $employee, Media $media): JsonResponse
    {
        $this->authorize('update', $employee);
        $this->employeeService->removeDocument($employee, $media);

        return $this->deleted('Employee document deleted successfully.');
    }
}
