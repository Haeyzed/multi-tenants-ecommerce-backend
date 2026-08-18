<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-configurable leave category.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_paid
 * @property bool $is_active
 * @property int $default_days
 * @property string|null $description
 */
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'is_active',
        'default_days',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_paid' => true,
        'is_active' => true,
        'default_days' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'default_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @param  Builder<LeaveType>  $query
     * @param  array{search?: string|null, is_active?: bool|null, is_paid?: bool|null}  $params
     * @return Builder<LeaveType>
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
            })
            ->when(array_key_exists('is_paid', $params) && $params['is_paid'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_paid', (bool) $params['is_paid']);
            });
    }

    /**
     * @param  Builder<LeaveType>  $query
     * @return Builder<LeaveType>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'code', 'is_active', 'is_paid', 'default_days', 'created_at', 'id'];
        $sort = $sort ?: 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'name';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
