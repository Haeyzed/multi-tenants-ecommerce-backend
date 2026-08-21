<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Overtime thresholds and rates for weekday, weekend, and holiday work.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_default
 * @property bool $is_active
 * @property int $weekday_rate_percent
 * @property int $weekend_rate_percent
 * @property int $holiday_rate_percent
 * @property int $daily_threshold_minutes
 * @property int $max_daily_minutes
 * @property int $weekly_threshold_minutes
 * @property int $weekly_rate_percent
 * @property int $round_to_minutes
 */
class OvertimePolicy extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_default',
        'is_active',
        'weekday_rate_percent',
        'weekend_rate_percent',
        'holiday_rate_percent',
        'daily_threshold_minutes',
        'max_daily_minutes',
        'weekly_threshold_minutes',
        'weekly_rate_percent',
        'round_to_minutes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'weekday_rate_percent' => 150,
        'weekend_rate_percent' => 200,
        'holiday_rate_percent' => 200,
        'daily_threshold_minutes' => 0,
        'max_daily_minutes' => 0,
        'weekly_threshold_minutes' => 0,
        'weekly_rate_percent' => 150,
        'round_to_minutes' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'weekday_rate_percent' => 'integer',
            'weekend_rate_percent' => 'integer',
            'holiday_rate_percent' => 'integer',
            'daily_threshold_minutes' => 'integer',
            'max_daily_minutes' => 'integer',
            'weekly_threshold_minutes' => 'integer',
            'weekly_rate_percent' => 'integer',
            'round_to_minutes' => 'integer',
        ];
    }

    /**
     * @return HasMany<WorkSchedule, $this>
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * @param  Builder<OvertimePolicy>  $query
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Builder<OvertimePolicy>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            });
    }

    /**
     * @param  Builder<OvertimePolicy>  $query
     * @return Builder<OvertimePolicy>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'code', 'is_default', 'is_active', 'created_at', 'id'];
        $sort = $sort ?: '-is_default';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'name';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
