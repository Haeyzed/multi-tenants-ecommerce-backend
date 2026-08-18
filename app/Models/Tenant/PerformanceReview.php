<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\PerformanceReviewStatus;
use Database\Factories\Tenant\PerformanceReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Employee review within a performance cycle.
 *
 * @property int $id
 * @property int $performance_cycle_id
 * @property int $employee_id
 * @property int|null $reviewer_id
 * @property string|null $rating
 * @property string|null $summary
 * @property PerformanceReviewStatus $status
 * @property Carbon|null $submitted_at
 */
class PerformanceReview extends Model
{
    /** @use HasFactory<PerformanceReviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_id',
        'employee_id',
        'reviewer_id',
        'rating',
        'summary',
        'status',
        'submitted_at',
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
            'performance_cycle_id' => 'integer',
            'employee_id' => 'integer',
            'reviewer_id' => 'integer',
            'rating' => 'decimal:1',
            'status' => PerformanceReviewStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PerformanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    /**
     * @param  Builder<PerformanceReview>  $query
     * @param  array{employee_id?: int|null, performance_cycle_id?: int|null, status?: string|null}  $params
     * @return Builder<PerformanceReview>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['employee_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('employee_id', $id);
            })
            ->when($params['performance_cycle_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('performance_cycle_id', $id);
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            });
    }

    /**
     * @param  Builder<PerformanceReview>  $query
     * @return Builder<PerformanceReview>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['rating', 'status', 'submitted_at', 'created_at', 'id'];
        $sort = $sort ?: '-id';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'id';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction);
    }
}
