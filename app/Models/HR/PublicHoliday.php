<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Observed public holiday used by the overtime engine.
 *
 * @property int $id
 * @property Carbon $observed_on
 * @property string $name
 * @property bool $repeats_annually
 */
class PublicHoliday extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'observed_on',
        'name',
        'repeats_annually',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'repeats_annually' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'repeats_annually' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PublicHoliday>  $query
     * @param  array{search?: string|null, year?: int|null}  $params
     * @return Builder<PublicHoliday>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($params['year'] ?? null, function (Builder $query, int $year): void {
                $query->where(function (Builder $query) use ($year): void {
                    $query->whereYear('observed_on', $year)
                        ->orWhere('repeats_annually', true);
                });
            });
    }

    /**
     * @param  Builder<PublicHoliday>  $query
     * @return Builder<PublicHoliday>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['observed_on', 'name', 'created_at', 'id'];
        $sort = $sort ?: 'observed_on';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'observed_on';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
