<?php

declare(strict_types=1);

namespace App\Models\Landlord\World;

use Illuminate\Database\Eloquent\Builder;
use Nnjeim\World\Models\Language as BaseLanguage;

/**
 * Landlord-scoped Language model backed by the Nnjeim World package.
 */
class Language extends BaseLanguage
{
    /**
     * @param  Builder<Language>  $query
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Builder<Language>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $filters = $params['filters'] ?? [];

        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['code'] ?? null, fn (Builder $query, string $code): Builder => $query->where('code', $code));
    }
}
