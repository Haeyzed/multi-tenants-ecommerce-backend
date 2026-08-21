<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentChangeType;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Events\EmployeeCreated;
use App\Events\EmployeeStatusChanged;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentRecord;
use App\Models\Tenant\User;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tenant HR employee profile CRUD (links to existing User).
 */
class EmployeeService
{
    /**
     * Create a new class instance.
     *
     * @param  MediaService  $mediaService
     * @param  HrSettingsService  $hrSettings
     * @param  WorkLocationService  $workLocations
     * @param  HrActivityService  $activities
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly HrSettingsService $hrSettings,
        private readonly WorkLocationService $workLocations,
        private readonly HrActivityService $activities,
    ) {}

    /**
     * search?: string|null, department_id?: int|null, designation_id?: int|null, employment_status?: string|null, sort?: string|null, per_page?: int|null }  $params
     *
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
            ->with($this->employeeRelations())
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * user_id: int, department_id?: int|null, designation_id?: int|null, manager_id?: int|null, work_schedule_id?: int|null, job_title?: string|null, employee_number?: string|null, employment_status?: EmploymentStatus|string|null, employment_type?: string|null, work_location?: string|null, hired_at?: string|null, notes?: string|null, bank_name?: string|null, bank_code?: string|null, account_number?: string|null, account_name?: string|null, tax_id?: string|null }  $data
     *
     * @param  array{
     *     user_id: int,
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     manager_id?: int|null,
     *     work_schedule_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string|null,
     *     employment_type?: string|null,
     *     work_location?: string|null,
     *     hired_at?: string|null,
     *     notes?: string|null,
     *     bank_name?: string|null,
     *     bank_code?: string|null,
     *     account_number?: string|null,
     *     account_name?: string|null,
     *     tax_id?: string|null
     * }  $data
     * @return Employee
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

        $payload = $this->syncDesignationDepartment($this->workLocations->applySnapshot([
            'user_id' => $user->id,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'work_schedule_id' => $data['work_schedule_id'] ?? null,
            'work_location_id' => $data['work_location_id'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'employee_number' => $data['employee_number'] ?? $this->nextEmployeeNumber(),
            'employment_status' => $data['employment_status'] ?? $this->hrSettings->defaultEmploymentStatus(),
            'employment_type' => $data['employment_type'] ?? null,
            'work_location' => $data['work_location'] ?? null,
            'hired_at' => $data['hired_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'pension_pin' => $data['pension_pin'] ?? null,
            'nhf_number' => $data['nhf_number'] ?? null,
            'nsitf_number' => $data['nsitf_number'] ?? null,
        ]));

        if (! Schema::hasColumn('employees', 'work_location_id')) {
            unset($payload['work_location_id']);
        }

        $employee = Employee::query()->create($payload)->load($this->employeeRelations());

        $this->recordEmployment($employee, EmploymentChangeType::Hired, $employee->hired_at?->toDateString());

        $this->activities->record($employee, 'created', null, [
            'employment_status' => $employee->employment_status->value,
        ]);

        event(new EmployeeCreated($employee));

        return $employee;
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Employee  $employee
     * @return Employee
     */
    public function show(Employee $employee): Employee
    {
        return $employee->load($this->employeeRelations());
    }

    /**
     * department_id?: int|null, designation_id?: int|null, manager_id?: int|null, work_schedule_id?: int|null, job_title?: string|null, employee_number?: string|null, employment_status?: EmploymentStatus|string, employment_type?: string|null, work_location?: string|null, hired_at?: string|null, notes?: string|null, bank_name?: string|null, bank_code?: string|null, account_number?: string|null, account_name?: string|null, tax_id?: string|null }  $data
     *
     * @param  Employee  $employee
     * @param  array{
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     manager_id?: int|null,
     *     work_schedule_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string,
     *     employment_type?: string|null,
     *     work_location?: string|null,
     *     hired_at?: string|null,
     *     notes?: string|null,
     *     bank_name?: string|null,
     *     bank_code?: string|null,
     *     account_number?: string|null,
     *     account_name?: string|null,
     *     tax_id?: string|null
     * }  $data
     * @return Employee
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
            'work_schedule_id' => $employee->work_schedule_id,
            'work_location_id' => $employee->work_location_id,
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

        $employee->fill($this->syncDesignationDepartment($this->workLocations->applySnapshot($data), $employee));
        $employee->save();

        $employee = $employee->fresh($this->employeeRelations()) ?? $employee;

        if ($employee->employment_status !== $previousStatus) {
            $this->recordEmployment($employee, EmploymentChangeType::StatusChanged);
            $this->activities->record($employee, 'status_changed', null, [
                'from' => $previousStatus->value,
                'to' => $employee->employment_status->value,
            ]);
            event(new EmployeeStatusChanged($employee, $previousStatus, $employee->employment_status));
        } elseif ($this->assignmentSnapshot($employee) !== $previousAssignment) {
            $this->recordEmployment($employee, EmploymentChangeType::AssignmentChanged);
        }

        return $employee;
    }

    /**
     * Delete a resource.
     *
     * @param  Employee  $employee
     * @return void
     */
    public function destroy(Employee $employee): void
    {
        $employee->clearMediaCollection();
        $employee->delete();
    }

    /**
     * Attach an HR document to the employee profile.
     *
     * @param  Employee  $employee
     * @param  UploadedFile  $file
     * @param  array{name?: string|null}  $options
     * @return Media
     */
    public function addDocument(Employee $employee, UploadedFile $file, array $options = []): Media
    {
        return $this->mediaService->add($employee, $file, 'documents', $options);
    }

    /**
     * Remove an HR document from the employee profile.
     *
     * @param  Employee  $employee
     * @param  Media  $media
     * @return void
     */
    public function removeDocument(Employee $employee, Media $media): void
    {
        $this->mediaService->remove($employee, $media);
    }

    /**
     * Sync designation department.
     *
     * @param  array<string, mixed>  $data
     * @param  ?Employee  $employee
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
     * Employment history.
     *
     * @param  Employee  $employee
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

    /**
     * Record employment.
     *
     * @param  Employee  $employee
     * @param  EmploymentChangeType  $changeType
     * @param  ?string  $effectiveOn
     * @return void
     */
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
     * Assignment snapshot.
     *
     * @param  Employee  $employee
     * @return array{department_id: int|null, designation_id: int|null, manager_id: int|null, work_schedule_id: int|null, work_location_id: int|null, job_title: string|null, employment_type: string|null, work_location: string|null}
     */
    protected function assignmentSnapshot(Employee $employee): array
    {
        return [
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_id' => $employee->manager_id,
            'work_schedule_id' => $employee->work_schedule_id,
            'work_location_id' => $employee->work_location_id,
            'job_title' => $employee->job_title,
            'employment_type' => $employee->employment_type?->value,
            'work_location' => $employee->work_location,
        ];
    }

    /**
     * Employee relations.
     *
     * @return list<string>
     */
    protected function employeeRelations(): array
    {
        $relations = ['user', 'department', 'designation', 'manager.user', 'workSchedule'];

        if (Schema::hasColumn('employees', 'work_location_id')) {
            $relations[] = 'workLocation';
        }

        return $relations;
    }

    /**
     * Assert status transition.
     *
     * @param  Employee  $employee
     * @param  EmploymentStatus|string  $status
     * @return void
     *
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
     * Assert manager.
     *
     * @param  mixed  $managerId
     * @param  ?int  $employeeId
     * @return void
     *
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

        if (! Employee::query()->assignableStaff()->whereKey($managerId)->exists()) {
            throw ValidationException::withMessages([
                'manager_id' => ['The selected manager must have an active employee profile.'],
            ]);
        }
    }

    /**
     * Next employee number.
     *
     * @return string
     */
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
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
