<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR employee profile CRUD (links to existing User).
 */
class EmployeeService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     department_id?: int|null,
     *     employment_status?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['user', 'department'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     user_id: int,
     *     department_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string|null,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function store(array $data): Employee
    {
        $user = User::query()->findOrFail($data['user_id']);

        if (Employee::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user already has an employee profile.'],
            ]);
        }

        return Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'employee_number' => $data['employee_number'] ?? null,
            'employment_status' => $data['employment_status'] ?? EmploymentStatus::Active,
            'hired_at' => $data['hired_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['user', 'department']);
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(['user', 'department']);
    }

    /**
     * @param  array{
     *     department_id?: int|null,
     *     job_title?: string|null,
     *     employee_number?: string|null,
     *     employment_status?: EmploymentStatus|string,
     *     hired_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        unset($data['user_id']);

        $employee->fill($data);
        $employee->save();

        return $employee->fresh(['user', 'department']) ?? $employee;
    }

    public function destroy(Employee $employee): void
    {
        $employee->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
