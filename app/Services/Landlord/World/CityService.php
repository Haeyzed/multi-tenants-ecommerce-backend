<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord City world data.
 */
class CityService
{
    /**
     * Retrieve a paginated list of cities.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, City>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return City::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single city by identifier.
     *
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): City
    {
        return City::query()->findOrFail($id);
    }

    /**
     * Retrieve city options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return City::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city): array => [
                'label' => $city->name,
                'value' => $city->id,
            ])
            ->values();
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
