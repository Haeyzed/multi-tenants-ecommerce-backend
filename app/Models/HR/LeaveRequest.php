<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Models\Tenant\User;
use Database\Factories\HR\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Employee leave request reviewed by tenant staff.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property LeaveStatus $status
 * @property string|null $reason
 * @property int|null $reviewer_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 */
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'status',
        'reason',
        'reviewer_id',
        'reviewed_at',
        'review_notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LeaveStatus::class,
            'reviewer_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Employee requesting leave.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Leave category for this request.
     *
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Tenant user who approved or rejected the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @param  array{
     *     employee_id?: int|null,
     *     leave_type_id?: int|null,
     *     type?: string|null,
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $params
     * @return Builder<LeaveRequest>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['employee_id'] ?? null, function (Builder $query, int $employeeId): void {
                $query->where('employee_id', $employeeId);
            })
            ->when($params['leave_type_id'] ?? null, function (Builder $query, int $leaveTypeId): void {
                $query->where('leave_type_id', $leaveTypeId);
            })
            ->when($params['type'] ?? null, function (Builder $query, string $type): void {
                $query->whereHas('leaveType', fn (Builder $leaveTypeQuery) => $leaveTypeQuery->where('code', $type));
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['from'] ?? null, function (Builder $query, string $from): void {
                $query->whereDate('end_date', '>=', $from);
            })
            ->when($params['to'] ?? null, function (Builder $query, string $to): void {
                $query->whereDate('start_date', '<=', $to);
            });
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['start_date', 'end_date', 'status', 'leave_type_id', 'created_at', 'id'];
        $sort = $sort ?: '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
