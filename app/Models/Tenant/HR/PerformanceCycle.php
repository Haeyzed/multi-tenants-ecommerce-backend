<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceCycleStatus;
use Database\Factories\HR\PerformanceCycleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Performance review window.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property PerformanceCycleStatus $status
 * @property string|null $description
 */
class PerformanceCycle extends Model
{
    /** @use HasFactory<PerformanceCycleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'status',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PerformanceCycleStatus::class,
        ];
    }

    /**
     * @return HasMany<PerformanceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    /**
     * @param  Builder<PerformanceCycle>  $query
     * @param  array{search?: string|null, status?: string|null}  $params
     * @return Builder<PerformanceCycle>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            });
    }

    /**
     * @param  Builder<PerformanceCycle>  $query
     * @return Builder<PerformanceCycle>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'starts_on', 'ends_on', 'status', 'created_at', 'id'];
        $sort = $sort ?: '-starts_on';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'starts_on';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
