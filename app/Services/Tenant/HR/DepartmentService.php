<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
     * @param  array{name: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function store(array $data): Department
    {
        return Department::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function show(Department $department): Department
    {
        return $department->loadCount('employees');
    }

    /**
     * @param  array{name?: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(Department $department, array $data): Department
    {
        $department->fill($data);
        $department->save();

        return $department->fresh() ?? $department;
    }

    public function destroy(Department $department): void
    {
        $department->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
