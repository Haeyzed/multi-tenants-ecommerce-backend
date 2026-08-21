<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord Currency world data.
 */
class CurrencyService
{
    /**
     * Retrieve a paginated list of currencies.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Currency>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Currency::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single currency by identifier.
     *
     * @param  int  $id
     * @return Currency
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Currency
    {
        return Currency::query()->findOrFail($id);
    }

    /**
     * Retrieve currency options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Currency::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Currency $currency): array => [
                'label' => "{$currency->name} ({$currency->code})",
                'value' => $currency->id,
            ])
            ->values();
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
