<?php

declare(strict_types=1);

namespace App\Models\Landlord\World;

use Illuminate\Database\Eloquent\Builder;
use Nnjeim\World\Models\Country as BaseCountry;

/**
 * Landlord-scoped Country model backed by the Nnjeim World package.
 */
class Country extends BaseCountry
{
    /**
     * @param  Builder<Country>  $query
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Builder<Country>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $filters = $params['filters'] ?? [];

        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('iso2', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['iso2'] ?? null, fn (Builder $query, string $iso2): Builder => $query->where('iso2', $iso2))
            ->when($filters['iso3'] ?? null, fn (Builder $query, string $iso3): Builder => $query->where('iso3', $iso3));
    }
}
