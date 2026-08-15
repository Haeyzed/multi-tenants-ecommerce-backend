<?php

declare(strict_types=1);

namespace App\Models\Landlord\World;

use Illuminate\Database\Eloquent\Builder;
use Nnjeim\World\Models\State as BaseState;

/**
 * Landlord-scoped State model backed by the Nnjeim World package.
 */
class State extends BaseState
{
    /**
     * @param  Builder<State>  $query
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Builder<State>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $filters = $params['filters'] ?? [];

        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($filters['country_id'] ?? null, fn (Builder $query, int|string $countryId): Builder => $query->where('country_id', $countryId))
            ->when($filters['country_code'] ?? null, fn (Builder $query, string $countryCode): Builder => $query->where('country_code', $countryCode));
    }
}
