<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\Designation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tenant HR designation (job title) CRUD.
 */
class DesignationService
{
    /**
     * @param  array{search?: string|null, department_id?: int|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Designation>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Designation::query()
            ->with('department')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(?int $departmentId = null): Collection
    {
        return Designation::query()
            ->where('is_active', true)
            ->when($departmentId !== null, fn ($query) => $query->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            }))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Designation $designation): array => [
                'label' => $designation->name,
                'value' => $designation->id,
            ])
            ->values();
    }

    /**
     * @param  array{department_id?: int|null, name: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function store(array $data): Designation
    {
        return Designation::query()->create([
            'department_id' => $data['department_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load('department');
    }

    public function show(Designation $designation): Designation
    {
        return $designation->loadCount('employees')->load('department');
    }

    /**
     * @param  array{department_id?: int|null, name?: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(Designation $designation, array $data): Designation
    {
        $designation->fill($data);
        $designation->save();

        return $designation->fresh(['department']) ?? $designation;
    }

    public function destroy(Designation $designation): void
    {
        $designation->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
