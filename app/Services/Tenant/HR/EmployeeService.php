<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentChangeType;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Events\EmployeeCreated;
use App\Events\EmployeeStatusChanged;
use App\Models\Tenant\Designation;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmploymentRecord;
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
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly HrSettingsService $hrSettings,
    ) {}

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
            ->with(['user', 'department', 'designation', 'manager.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     user_id: int,
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     manager_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string|null,
     *     employment_type?: string|null,
     *     work_location?: string|null,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): Employee
    {
        $this->hrSettings->assertModuleEnabled();

        $user = User::query()->findOrFail($data['user_id']);

        if (Employee::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user already has an employee profile.'],
            ]);
        }

        $this->assertManager($data['manager_id'] ?? null);

        $payload = $this->syncDesignationDepartment([
            'user_id' => $user->id,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'employee_number' => $data['employee_number'] ?? $this->nextEmployeeNumber(),
            'employment_status' => $data['employment_status'] ?? $this->hrSettings->defaultEmploymentStatus(),
            'employment_type' => $data['employment_type'] ?? null,
            'work_location' => $data['work_location'] ?? null,
            'hired_at' => $data['hired_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $employee = Employee::query()->create($payload)->load(['user', 'department', 'designation', 'manager.user']);

        $this->recordEmployment($employee, EmploymentChangeType::Hired, $employee->hired_at?->toDateString());

        event(new EmployeeCreated($employee));

        return $employee;
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(['user', 'department', 'designation', 'manager.user']);
    }

    /**
     * @param  array{
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     manager_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string,
     *     employment_type?: string|null,
     *     work_location?: string|null,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function update(Employee $employee, array $data): Employee
    {
        $this->hrSettings->assertModuleEnabled();

        unset($data['user_id']);

        $previousStatus = $employee->employment_status;
        $previousAssignment = [
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_id' => $employee->manager_id,
            'job_title' => $employee->job_title,
            'employment_type' => $employee->employment_type?->value,
            'work_location' => $employee->work_location,
        ];

        if (array_key_exists('manager_id', $data)) {
            $this->assertManager($data['manager_id'], $employee->id);
        }

        if (array_key_exists('employment_status', $data) && $data['employment_status'] !== null) {
            $this->assertStatusTransition($employee, $data['employment_status']);
            $target = $data['employment_status'] instanceof EmploymentStatus
                ? $data['employment_status']
                : EmploymentStatus::from($data['employment_status']);

            if ($target->isTerminal() && $employee->terminated_at === null) {
                $data['terminated_at'] = now()->toDateString();
            }

            if (! $target->isTerminal()) {
                $data['terminated_at'] = null;
            }
        }

        $employee->fill($this->syncDesignationDepartment($data, $employee));
        $employee->save();

        $employee = $employee->fresh(['user', 'department', 'designation', 'manager.user']) ?? $employee;

        if ($employee->employment_status !== $previousStatus) {
            $this->recordEmployment($employee, EmploymentChangeType::StatusChanged);
            event(new EmployeeStatusChanged($employee, $previousStatus, $employee->employment_status));
        } elseif ($this->assignmentSnapshot($employee) !== $previousAssignment) {
            $this->recordEmployment($employee, EmploymentChangeType::AssignmentChanged);
        }

        return $employee;
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
     * @return list<EmploymentRecord>
     */
    public function employmentHistory(Employee $employee): array
    {
        return $employee->employmentRecords()
            ->with(['department', 'designation', 'manager.user'])
            ->orderByDesc('effective_on')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    protected function recordEmployment(Employee $employee, EmploymentChangeType $changeType, ?string $effectiveOn = null): void
    {
        EmploymentRecord::query()->create([
            'employee_id' => $employee->id,
            'change_type' => $changeType,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_id' => $employee->manager_id,
            'job_title' => $employee->job_title,
            'employment_status' => $employee->employment_status,
            'employment_type' => $employee->employment_type,
            'work_location' => $employee->work_location,
            'effective_on' => $effectiveOn ?? now()->toDateString(),
        ]);
    }

    /**
     * @return array{department_id: int|null, designation_id: int|null, manager_id: int|null, job_title: string|null, employment_type: string|null, work_location: string|null}
     */
    protected function assignmentSnapshot(Employee $employee): array
    {
        return [
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_id' => $employee->manager_id,
            'job_title' => $employee->job_title,
            'employment_type' => $employee->employment_type?->value,
            'work_location' => $employee->work_location,
        ];
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
     * @throws ValidationException
     */
    protected function assertManager(mixed $managerId, ?int $employeeId = null): void
    {
        if ($managerId === null || $managerId === '') {
            return;
        }

        $managerId = (int) $managerId;

        if ($employeeId !== null && $managerId === $employeeId) {
            throw ValidationException::withMessages([
                'manager_id' => ['An employee cannot report to themselves.'],
            ]);
        }

        if (! Employee::query()->whereKey($managerId)->exists()) {
            throw ValidationException::withMessages([
                'manager_id' => ['The selected manager is invalid.'],
            ]);
        }
    }

    protected function nextEmployeeNumber(): string
    {
        $prefix = $this->hrSettings->employeeCodePrefix();
        $latest = Employee::withTrashed()
            ->where('employee_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('employee_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
