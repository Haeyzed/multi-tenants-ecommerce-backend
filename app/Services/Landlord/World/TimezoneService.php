<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\Timezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord Timezone world data.
 */
class TimezoneService
{
    /**
     * Retrieve a paginated list of timezones.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Timezone>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Timezone::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single timezone by identifier.
     *
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Timezone
    {
        return Timezone::query()->findOrFail($id);
    }

    /**
     * Retrieve timezone options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Timezone::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Timezone $timezone): array => [
                'label' => $timezone->name,
                'value' => $timezone->id,
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
