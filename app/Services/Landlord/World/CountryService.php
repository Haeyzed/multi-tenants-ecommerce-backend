<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\Country;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord Country world data.
 */
class CountryService
{
    /**
     * Retrieve a paginated list of countries.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Country>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Country::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single country by identifier.
     *
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Country
    {
        return Country::query()->findOrFail($id);
    }

    /**
     * Retrieve country options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Country::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Country $country): array => [
                'label' => $country->name,
                'value' => $country->id,
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
