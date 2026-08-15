<?php

declare(strict_types=1);

namespace App\Models\Landlord\World;

use Illuminate\Database\Eloquent\Builder;
use Nnjeim\World\Models\Timezone as BaseTimezone;

/**
 * Landlord-scoped Timezone model backed by the Nnjeim World package.
 */
class Timezone extends BaseTimezone
{
    /**
     * @param  Builder<Timezone>  $query
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Builder<Timezone>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $filters = $params['filters'] ?? [];

        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($filters['country_id'] ?? null, fn (Builder $query, int|string $countryId): Builder => $query->where('country_id', $countryId));
    }
}
