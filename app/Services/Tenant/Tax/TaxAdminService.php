<?php

declare(strict_types=1);

namespace App\Services\Tenant\Tax;

use App\Enums\Tenant\Tax\TaxAppliesTo;
use App\Models\Tenant\Tax;
use App\Models\Tenant\TaxRule;
use App\Models\Tenant\TaxZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD for tax configuration entities.
 */
class TaxAdminService
{
    /**
     * List taxes.
     *
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Tax>
     */
    public function listTaxes(array $params = []): LengthAwarePaginator
    {
        $query = Tax::query()->with(['rates', 'rules.taxZone'])->orderBy('priority')->orderBy('name');

        if (isset($params['is_active'])) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * name: string, code: string, is_active?: bool, is_inclusive?: bool, priority?: int, rates?: list<array{rate: string|float, effective_from?: string|null, effective_to?: string|null}> }  $data
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     is_active?: bool,
     *     is_inclusive?: bool,
     *     priority?: int,
     *     rates?: list<array{rate: string|float, effective_from?: string|null, effective_to?: string|null}>
     * }  $data
     * @return Tax
     */
    public function storeTax(array $data): Tax
    {
        return DB::transaction(function () use ($data): Tax {
            $tax = Tax::query()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'] ?? true,
                'is_inclusive' => $data['is_inclusive'] ?? false,
                'priority' => $data['priority'] ?? 0,
            ]);

            foreach ($data['rates'] ?? [] as $rate) {
                $tax->rates()->create([
                    'rate' => $rate['rate'],
                    'effective_from' => $rate['effective_from'] ?? null,
                    'effective_to' => $rate['effective_to'] ?? null,
                ]);
            }

            return $tax->load(['rates', 'rules.taxZone']);
        });
    }

    /**
     * Show tax.
     *
     * @param  Tax  $tax
     * @return Tax
     */
    public function showTax(Tax $tax): Tax
    {
        return $tax->load(['rates', 'rules.taxZone']);
    }

    /**
     * name?: string, code?: string, is_active?: bool, is_inclusive?: bool, priority?: int, rates?: list<array{rate: string|float, effective_from?: string|null, effective_to?: string|null}> }  $data
     *
     * @param  Tax  $tax
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     is_active?: bool,
     *     is_inclusive?: bool,
     *     priority?: int,
     *     rates?: list<array{rate: string|float, effective_from?: string|null, effective_to?: string|null}>
     * }  $data
     * @return Tax
     */
    public function updateTax(Tax $tax, array $data): Tax
    {
        return DB::transaction(function () use ($tax, $data): Tax {
            $tax->fill(collect($data)->except(['rates'])->all());
            $tax->save();

            if (array_key_exists('rates', $data)) {
                $tax->rates()->delete();
                foreach ($data['rates'] ?? [] as $rate) {
                    $tax->rates()->create([
                        'rate' => $rate['rate'],
                        'effective_from' => $rate['effective_from'] ?? null,
                        'effective_to' => $rate['effective_to'] ?? null,
                    ]);
                }
            }

            return $tax->fresh(['rates', 'rules.taxZone']) ?? $tax;
        });
    }

    /**
     * Destroy tax.
     *
     * @param  Tax  $tax
     * @return void
     */
    public function destroyTax(Tax $tax): void
    {
        $tax->delete();
    }

    /**
     * List zones.
     *
     * @param  array{is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, TaxZone>
     */
    public function listZones(array $params = []): LengthAwarePaginator
    {
        $query = TaxZone::query()->with(['locations', 'rules.tax'])->orderBy('name');

        if (isset($params['is_active'])) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * name: string, is_active?: bool, locations?: list<array{country_id?: int|null, state_id?: int|null, city_id?: int|null}> }  $data
     *
     * @param  array{
     *     name: string,
     *     is_active?: bool,
     *     locations?: list<array{country_id?: int|null, state_id?: int|null, city_id?: int|null}>
     * }  $data
     * @return TaxZone
     */
    public function storeZone(array $data): TaxZone
    {
        return DB::transaction(function () use ($data): TaxZone {
            $zone = TaxZone::query()->create([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['locations'] ?? [] as $location) {
                $zone->locations()->create([
                    'country_id' => $location['country_id'] ?? null,
                    'state_id' => $location['state_id'] ?? null,
                    'city_id' => $location['city_id'] ?? null,
                ]);
            }

            return $zone->load(['locations', 'rules.tax']);
        });
    }

    /**
     * Show zone.
     *
     * @param  TaxZone  $zone
     * @return TaxZone
     */
    public function showZone(TaxZone $zone): TaxZone
    {
        return $zone->load(['locations', 'rules.tax']);
    }

    /**
     * name?: string, is_active?: bool, locations?: list<array{country_id?: int|null, state_id?: int|null, city_id?: int|null}> }  $data
     *
     * @param  TaxZone  $zone
     * @param  array{
     *     name?: string,
     *     is_active?: bool,
     *     locations?: list<array{country_id?: int|null, state_id?: int|null, city_id?: int|null}>
     * }  $data
     * @return TaxZone
     */
    public function updateZone(TaxZone $zone, array $data): TaxZone
    {
        return DB::transaction(function () use ($zone, $data): TaxZone {
            $zone->fill(collect($data)->except(['locations'])->all());
            $zone->save();

            if (array_key_exists('locations', $data)) {
                $zone->locations()->delete();
                foreach ($data['locations'] ?? [] as $location) {
                    $zone->locations()->create([
                        'country_id' => $location['country_id'] ?? null,
                        'state_id' => $location['state_id'] ?? null,
                        'city_id' => $location['city_id'] ?? null,
                    ]);
                }
            }

            return $zone->fresh(['locations', 'rules.tax']) ?? $zone;
        });
    }

    /**
     * Destroy zone.
     *
     * @param  TaxZone  $zone
     * @return void
     */
    public function destroyZone(TaxZone $zone): void
    {
        if ($zone->rules()->exists()) {
            throw ValidationException::withMessages([
                'tax_zone' => 'Cannot delete a tax zone that has active rules.',
            ]);
        }

        $zone->delete();
    }

    /**
     * tax_id: int, tax_zone_id: int, applies_to: string, is_active?: bool }  $data
     *
     * @param  array{
     *     tax_id: int,
     *     tax_zone_id: int,
     *     applies_to: string,
     *     is_active?: bool
     * }  $data
     * @return TaxRule
     */
    public function storeRule(array $data): TaxRule
    {
        return TaxRule::query()->create([
            'tax_id' => (int) $data['tax_id'],
            'tax_zone_id' => (int) $data['tax_zone_id'],
            'applies_to' => TaxAppliesTo::from($data['applies_to']),
            'is_active' => $data['is_active'] ?? true,
        ])->load(['tax', 'taxZone']);
    }

    /**
     * Update rule.
     *
     * @param  TaxRule  $rule
     * @param  array{applies_to?: string, is_active?: bool}  $data
     * @return TaxRule
     */
    public function updateRule(TaxRule $rule, array $data): TaxRule
    {
        if (isset($data['applies_to'])) {
            $rule->applies_to = TaxAppliesTo::from($data['applies_to']);
        }

        $rule->fill(collect($data)->except(['applies_to'])->all());
        $rule->save();

        return $rule->fresh(['tax', 'taxZone']) ?? $rule;
    }

    /**
     * Destroy rule.
     *
     * @param  TaxRule  $rule
     * @return void
     */
    public function destroyRule(TaxRule $rule): void
    {
        $rule->delete();
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
