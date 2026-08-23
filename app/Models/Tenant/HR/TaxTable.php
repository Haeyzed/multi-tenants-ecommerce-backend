<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Country PAYE table for a tax year.
 *
 * @property int $id
 * @property string $country_code
 * @property string $name
 * @property int $year
 * @property string $currency
 * @property bool $is_active
 * @property string $relief_percent
 * @property string $relief_fixed
 * @property string $relief_minimum_percent
 * @property string $personal_allowance
 */
class TaxTable extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_code',
        'name',
        'year',
        'currency',
        'is_active',
        'relief_percent',
        'relief_fixed',
        'relief_minimum_percent',
        'personal_allowance',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'NGN',
        'is_active' => true,
        'relief_percent' => '0.00',
        'relief_fixed' => '0.00',
        'relief_minimum_percent' => '0.00',
        'personal_allowance' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
            'relief_percent' => 'decimal:2',
            'relief_fixed' => 'decimal:2',
            'relief_minimum_percent' => 'decimal:2',
            'personal_allowance' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<TaxTableBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(TaxTableBand::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<TaxTable>  $query
     * @param  array{search?: string|null, country_code?: string|null, year?: int|null, is_active?: bool|null}  $params
     * @return Builder<TaxTable>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($params['country_code'] ?? null, function (Builder $query, string $country): void {
                $query->where('country_code', strtoupper($country));
            })
            ->when($params['year'] ?? null, function (Builder $query, int $year): void {
                $query->where('year', $year);
            })
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            });
    }

    /**
     * @param  Builder<TaxTable>  $query
     * @return Builder<TaxTable>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['country_code', 'name', 'year', 'is_active', 'created_at', 'id'];
        $sort = $sort ?: '-year';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'year';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
