<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Events\EmployeeCreated;
use App\Models\Tenant\Designation;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tenant HR employee profile CRUD (links to existing User).
 */
class EmployeeService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     employment_status?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     user_id: int,
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string|null,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): Employee
    {
        $user = User::query()->findOrFail($data['user_id']);

        if (Employee::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user already has an employee profile.'],
            ]);
        }

        $payload = $this->syncDesignationDepartment([
            'user_id' => $user->id,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'employee_number' => $data['employee_number'] ?? null,
            'employment_status' => $data['employment_status'] ?? EmploymentStatus::Active,
            'hired_at' => $data['hired_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $employee = Employee::query()->create($payload)->load(['user', 'department', 'designation']);

        event(new EmployeeCreated($employee));

        return $employee;
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(['user', 'department', 'designation']);
    }

    /**
     * @param  array{
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function update(Employee $employee, array $data): Employee
    {
        unset($data['user_id']);

        if (array_key_exists('employment_status', $data) && $data['employment_status'] !== null) {
            $this->assertStatusTransition($employee, $data['employment_status']);
        }

        $employee->fill($this->syncDesignationDepartment($data, $employee));
        $employee->save();

        return $employee->fresh(['user', 'department', 'designation']) ?? $employee;
    }

    public function destroy(Employee $employee): void
    {
        $employee->clearMediaCollection();
        $employee->delete();
    }

    /**
     * Attach an HR document to the employee profile.
     *
     * @param  array{name?: string|null}  $options
     */
    public function addDocument(Employee $employee, UploadedFile $file, array $options = []): Media
    {
        return $this->mediaService->add($employee, $file, 'documents', $options);
    }

    /**
     * Remove an HR document from the employee profile.
     */
    public function removeDocument(Employee $employee, Media $media): void
    {
        $this->mediaService->remove($employee, $media);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function syncDesignationDepartment(array $data, ?Employee $employee = null): array
    {
        $designationId = array_key_exists('designation_id', $data)
            ? $data['designation_id']
            : $employee?->designation_id;

        if ($designationId === null) {
            return $data;
        }

        $designation = Designation::query()->find($designationId);

        if ($designation === null) {
            throw ValidationException::withMessages([
                'designation_id' => ['The selected designation is invalid.'],
            ]);
        }

        $departmentId = array_key_exists('department_id', $data)
            ? $data['department_id']
            : $employee?->department_id;

        if ($designation->department_id !== null) {
            if ($departmentId === null) {
                $data['department_id'] = $designation->department_id;
            } elseif ((int) $departmentId !== $designation->department_id) {
                throw ValidationException::withMessages([
                    'designation_id' => ['The designation does not belong to the selected department.'],
                ]);
            }
        }

        return $data;
    }

    /**
     * @throws ValidationException
     */
    protected function assertStatusTransition(Employee $employee, EmploymentStatus|string $status): void
    {
        $target = $status instanceof EmploymentStatus
            ? $status
            : EmploymentStatus::from($status);

        if (! $employee->employment_status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'employment_status' => ['This employment status transition is not allowed.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
