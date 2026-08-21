<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\Employee;
use App\Models\HR\JobOpening;
use App\Models\HR\WorkLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR work location CRUD.
 */
class WorkLocationService
{
    /**
     * @param  array{search?: string|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, WorkLocation>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return WorkLocation::query()
            ->withCount(['employees', 'jobOpenings'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(): Collection
    {
        return WorkLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (WorkLocation $location): array => [
                'label' => $location->name,
                'value' => $location->id,
            ])
            ->values();
    }

    /**
     * @param  array{name: string, code?: string|null, address?: string|null, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): WorkLocation
    {
        $this->assertUniqueName($data['name']);

        return WorkLocation::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function show(WorkLocation $location): WorkLocation
    {
        return $location->loadCount(['employees', 'jobOpenings']);
    }

    /**
     * @param  array{name?: string, code?: string|null, address?: string|null, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function update(WorkLocation $location, array $data): WorkLocation
    {
        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $this->assertUniqueName($data['name'], $location->id);
        }

        $location->fill($data);
        $location->save();

        return $location->fresh() ?? $location;
    }

    /**
     * @throws ValidationException
     */
    public function destroy(WorkLocation $location): void
    {
        if (Employee::query()->where('work_location_id', $location->id)->exists()
            || JobOpening::query()->where('work_location_id', $location->id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This work location is in use and cannot be deleted.'],
            ]);
        }

        $location->delete();
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueName(string $name, ?int $ignoreId = null): void
    {
        $exists = WorkLocation::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A work location with this name already exists.'],
            ]);
        }
    }

    /**
     * Copy the configured location name onto the legacy snapshot string when assigned.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applySnapshot(array $data, string $idKey = 'work_location_id', string $labelKey = 'work_location'): array
    {
        if (! Schema::hasTable('work_locations') || ! array_key_exists($idKey, $data) || $data[$idKey] === null || $data[$idKey] === '') {
            return $data;
        }

        $location = WorkLocation::query()->find((int) $data[$idKey]);

        if ($location !== null && (! array_key_exists($labelKey, $data) || $data[$labelKey] === null || $data[$labelKey] === '')) {
            $data[$labelKey] = $location->name;
        }

        return $data;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
