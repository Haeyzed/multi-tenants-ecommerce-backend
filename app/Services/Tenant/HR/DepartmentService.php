<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\HR\Department;
use App\Models\Tenant\HR\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR department CRUD.
 */
class DepartmentService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Department>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Department::query()
            ->with('manager.user')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'label' => $department->name,
                'value' => $department->id,
            ])
            ->values();
    }

    /**
     * Create a resource.
     *
     * @param  array{name: string, code?: string|null, description?: string|null, manager_id?: int|null, is_active?: bool}  $data
     * @return Department
     *
     * @throws ValidationException
     */
    public function store(array $data): Department
    {
        $this->assertUniqueName($data['name']);
        $this->assertManager($data['manager_id'] ?? null);

        return Department::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load('manager.user');
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Department  $department
     * @return Department
     */
    public function show(Department $department): Department
    {
        return $department->loadCount('employees')->load('manager.user');
    }

    /**
     * Update a resource.
     *
     * @param  Department  $department
     * @param  array{name?: string, code?: string|null, description?: string|null, manager_id?: int|null, is_active?: bool}  $data
     * @return Department
     *
     * @throws ValidationException
     */
    public function update(Department $department, array $data): Department
    {
        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $this->assertUniqueName($data['name'], $department->id);
        }

        if (array_key_exists('manager_id', $data)) {
            $this->assertManager($data['manager_id']);
        }

        $department->fill($data);
        $department->save();

        return $department->fresh(['manager.user']) ?? $department;
    }

    /**
     * Delete a resource.
     *
     * @param  Department  $department
     * @return void
     */
    public function destroy(Department $department): void
    {
        $department->delete();
    }

    /**
     * Assert unique name.
     *
     * @param  string  $name
     * @param  ?int  $ignoreId
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertUniqueName(string $name, ?int $ignoreId = null): void
    {
        $exists = Department::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A department with this name already exists.'],
            ]);
        }
    }

    /**
     * Assert manager.
     *
     * @param  mixed  $managerId
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertManager(mixed $managerId): void
    {
        if ($managerId === null || $managerId === '') {
            return;
        }

        if (! Employee::query()->assignableStaff()->whereKey((int) $managerId)->exists()) {
            throw ValidationException::withMessages([
                'manager_id' => ['The selected department manager must have an active employee profile.'],
            ]);
        }
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
