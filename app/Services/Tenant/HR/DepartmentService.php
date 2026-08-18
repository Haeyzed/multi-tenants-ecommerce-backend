<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR department CRUD.
 */
class DepartmentService
{
    /**
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
     * @param  array{name: string, code?: string|null, description?: string|null, manager_id?: int|null, is_active?: bool}  $data
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

    public function show(Department $department): Department
    {
        return $department->loadCount('employees')->load('manager.user');
    }

    /**
     * @param  array{name?: string, code?: string|null, description?: string|null, manager_id?: int|null, is_active?: bool}  $data
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

    public function destroy(Department $department): void
    {
        $department->delete();
    }

    /**
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
     * @throws ValidationException
     */
    protected function assertManager(mixed $managerId): void
    {
        if ($managerId === null || $managerId === '') {
            return;
        }

        if (! Employee::query()->whereKey((int) $managerId)->exists()) {
            throw ValidationException::withMessages([
                'manager_id' => ['The selected department manager is invalid.'],
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
