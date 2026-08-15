<?php

declare(strict_types=1);

namespace App\Services\Landlord\Feature;

use App\Models\Landlord\Feature;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Landlord feature catalog CRUD.
 */
class FeatureService
{
    /**
     * Retrieve a paginated list of features.
     *
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Feature>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Feature::query()
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve feature options as label/value pairs for select inputs.
     *
     * Value is the stable feature slug used by plan sync and entitlement checks.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: string}>
     */
    public function options(array $params = []): Collection
    {
        return Feature::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Feature $feature): array => [
                'label' => $feature->name,
                'value' => $feature->slug,
            ])
            ->values();
    }

    /**
     * Create a feature.
     *
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function store(array $data): Feature
    {
        return Feature::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Show a feature.
     */
    public function show(Feature $feature): Feature
    {
        return $feature->load('plans');
    }

    /**
     * Update a feature.
     *
     * @param  array{name?: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(Feature $feature, array $data): Feature
    {
        $feature->fill($data);
        $feature->save();

        return $feature->fresh('plans') ?? $feature;
    }

    /**
     * Delete a feature.
     */
    public function destroy(Feature $feature): void
    {
        $feature->delete();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
