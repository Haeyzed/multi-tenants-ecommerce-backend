<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named weekly work pattern assigned to employees.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_default
 * @property bool $is_active
 * @property int|null $overtime_policy_id
 */
class WorkSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_default',
        'is_active',
        'overtime_policy_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'overtime_policy_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<WorkScheduleDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('weekday')->orderBy('id');
    }

    /**
     * @return BelongsTo<OvertimePolicy, $this>
     */
    public function overtimePolicy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @param  Builder<WorkSchedule>  $query
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Builder<WorkSchedule>
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
     * @param  Builder<WorkSchedule>  $query
     * @return Builder<WorkSchedule>
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
