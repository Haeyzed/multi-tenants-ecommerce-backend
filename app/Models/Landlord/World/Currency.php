<?php

declare(strict_types=1);

namespace App\Models\Landlord\World;

use Illuminate\Database\Eloquent\Builder;
use Nnjeim\World\Models\Currency as BaseCurrency;

/**
 * Landlord-scoped Currency model backed by the Nnjeim World package.
 */
class Currency extends BaseCurrency
{
    /**
     * @param  Builder<Currency>  $query
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Builder<Currency>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $filters = $params['filters'] ?? [];

        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['country_id'] ?? null, fn (Builder $query, int|string $countryId): Builder => $query->where('country_id', $countryId))
            ->when($filters['code'] ?? null, fn (Builder $query, string $code): Builder => $query->where('code', $code));
    }
}
