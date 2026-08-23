<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\HR\Designation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tenant HR designation (job title) CRUD.
 */
class DesignationService
{
    /**
     * Retrieve a paginated list of resources.
     *
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
     * Return options for select inputs.
     *
     * @param  ?int  $departmentId
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
     * Create a resource.
     *
     * @param  array{department_id?: int|null, name: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     * @return Designation
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

    /**
     * Retrieve a single resource.
     *
     * @param  Designation  $designation
     * @return Designation
     */
    public function show(Designation $designation): Designation
    {
        return $designation->loadCount('employees')->load('department');
    }

    /**
     * Update a resource.
     *
     * @param  Designation  $designation
     * @param  array{department_id?: int|null, name?: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     * @return Designation
     */
    public function update(Designation $designation, array $data): Designation
    {
        $designation->fill($data);
        $designation->save();

        return $designation->fresh(['department']) ?? $designation;
    }

    /**
     * Delete a resource.
     *
     * @param  Designation  $designation
     * @return void
     */
    public function destroy(Designation $designation): void
    {
        $designation->delete();
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
