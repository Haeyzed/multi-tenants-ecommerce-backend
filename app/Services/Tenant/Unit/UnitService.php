<?php

declare(strict_types=1);

namespace App\Services\Tenant\Unit;

use App\Models\Tenant\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant measurement unit catalog operations.
 */
class UnitService
{
    /**
     * Paginate units with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Unit>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Unit::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Unit options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Unit::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Unit $unit): array => [
                'label' => $unit->name,
                'value' => $unit->id,
            ])
            ->values();
    }

    /**
     * Create a measurement unit.
     *
     * @param  array{
     *     name: string,
     *     short_name?: string|null,
     *     code: string,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function store(array $data): Unit
    {
        return Unit::query()->create([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'code' => $data['code'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Retrieve a unit.
     */
    public function show(Unit $unit): Unit
    {
        return $unit;
    }

    /**
     * Update a measurement unit.
     *
     * @param  array{
     *     name?: string,
     *     short_name?: string|null,
     *     code?: string,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        $unit->fill($data);
        $unit->save();

        return $unit->fresh() ?? $unit;
    }

    /**
     * Delete a unit when no products or variants reference it.
     *
     * @throws ValidationException
     */
    public function destroy(Unit $unit): void
    {
        if ($unit->products()->exists() || $unit->variants()->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'Cannot delete a unit that is referenced by products or variants.',
            ]);
        }

        DB::transaction(fn (): bool => $unit->delete());
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
