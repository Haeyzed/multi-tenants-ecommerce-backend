<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\TaxTable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Country PAYE tables with progressive bands.
 */
class TaxTableService
{
    /**
     * search?: string|null, country_code?: string|null, year?: int|null, is_active?: bool|null, sort?: string|null, per_page?: int|null }  $params
     *
     * @param  array{
     *     search?: string|null,
     *     country_code?: string|null,
     *     year?: int|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, TaxTable>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->ensureDefaults();

        return TaxTable::query()
            ->with('bands')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * country_code: string, name: string, year: int, currency?: string|null, is_active?: bool, relief_percent?: string|float|int, relief_fixed?: string|float|int, relief_minimum_percent?: string|float|int, personal_allowance?: string|float|int, bands?: list<array{min_amount: string|float|int, max_amount?: string|float|int|null, rate_percent: string|float|int, sort_order?: int}> }  $data
     *
     * @param  array{
     *     country_code: string,
     *     name: string,
     *     year: int,
     *     currency?: string|null,
     *     is_active?: bool,
     *     relief_percent?: string|float|int,
     *     relief_fixed?: string|float|int,
     *     relief_minimum_percent?: string|float|int,
     *     personal_allowance?: string|float|int,
     *     bands?: list<array{min_amount: string|float|int, max_amount?: string|float|int|null, rate_percent: string|float|int, sort_order?: int}>
     * }  $data
     * @return TaxTable
     */
    public function store(array $data): TaxTable
    {
        $this->ensureDefaults();

        return DB::transaction(function () use ($data): TaxTable {
            $table = TaxTable::query()->create($this->tablePayload($data));
            $this->syncBands($table, $data['bands'] ?? []);

            return $this->show($table);
        });
    }

    /**
     * Retrieve a single resource.
     *
     * @param  TaxTable  $table
     * @return TaxTable
     */
    public function show(TaxTable $table): TaxTable
    {
        return $table->load('bands');
    }

    /**
     * Update a resource.
     *
     * @param  TaxTable  $table
     * @param  array<string, mixed>  $data
     * @return TaxTable
     */
    public function update(TaxTable $table, array $data): TaxTable
    {
        return DB::transaction(function () use ($table, $data): TaxTable {
            $table->fill($this->tablePayload($data, $table));
            $table->save();

            if (array_key_exists('bands', $data) && is_array($data['bands'])) {
                $this->syncBands($table, $data['bands']);
            }

            return $this->show($table);
        });
    }

    /**
     * Delete a resource.
     *
     * @param  TaxTable  $table
     * @return void
     */
    public function destroy(TaxTable $table): void
    {
        $table->bands()->delete();
        $table->delete();
    }

    /**
     * Seed Nigeria PAYE (Finance Act style) when the tenant has no tax tables.
     *
     * @return void
     */
    public function ensureDefaults(): void
    {
        if (TaxTable::query()->exists()) {
            return;
        }

        $table = TaxTable::query()->create([
            'country_code' => 'NG',
            'name' => 'Nigeria PAYE',
            'year' => (int) now()->year,
            'currency' => 'NGN',
            'is_active' => true,
            'relief_percent' => '20.00',
            'relief_fixed' => '200000.00',
            'relief_minimum_percent' => '1.00',
            'personal_allowance' => '0.00',
        ]);

        $bands = [
            ['min_amount' => '0.00', 'max_amount' => '300000.00', 'rate_percent' => '7.00'],
            ['min_amount' => '300000.00', 'max_amount' => '600000.00', 'rate_percent' => '11.00'],
            ['min_amount' => '600000.00', 'max_amount' => '1100000.00', 'rate_percent' => '15.00'],
            ['min_amount' => '1100000.00', 'max_amount' => '1600000.00', 'rate_percent' => '19.00'],
            ['min_amount' => '1600000.00', 'max_amount' => '3200000.00', 'rate_percent' => '21.00'],
            ['min_amount' => '3200000.00', 'max_amount' => null, 'rate_percent' => '24.00'],
        ];

        $this->syncBands($table, $bands);
    }

    /**
     * Table payload.
     *
     * @param  array<string, mixed>  $data
     * @param  ?TaxTable  $table
     * @return array<string, mixed>
     */
    protected function tablePayload(array $data, ?TaxTable $table = null): array
    {
        $payload = [];

        foreach (['name', 'year', 'is_active', 'relief_percent', 'relief_fixed', 'relief_minimum_percent', 'personal_allowance'] as $key) {
            if (array_key_exists($key, $data) || $table === null) {
                $payload[$key] = $data[$key] ?? $table?->{$key};
            }
        }

        if (array_key_exists('country_code', $data) || $table === null) {
            $payload['country_code'] = strtoupper((string) ($data['country_code'] ?? $table?->country_code));
        }

        if (array_key_exists('currency', $data) || $table === null) {
            $payload['currency'] = strtoupper((string) ($data['currency'] ?? $table?->currency ?? 'NGN'));
        }

        if ($table === null) {
            $payload['is_active'] = $data['is_active'] ?? true;
        }

        return $payload;
    }

    /**
     * Sync bands.
     *
     * @param  TaxTable  $table
     * @param  list<array{min_amount: string|float|int, max_amount?: string|float|int|null, rate_percent: string|float|int, sort_order?: int}>  $bands
     * @return void
     */
    protected function syncBands(TaxTable $table, array $bands): void
    {
        $table->bands()->delete();

        foreach ($bands as $index => $band) {
            $table->bands()->create([
                'sort_order' => (int) ($band['sort_order'] ?? $index),
                'min_amount' => $band['min_amount'],
                'max_amount' => $band['max_amount'] ?? null,
                'rate_percent' => $band['rate_percent'],
            ]);
        }
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
