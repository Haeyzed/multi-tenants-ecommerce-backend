<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\AttendanceStatus;
use Database\Factories\Tenant\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attendance record per employee per work date.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property AttendanceStatus $status
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property int $overtime_minutes
 * @property int $overtime_rate_percent
 * @property string|null $notes
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'work_date',
        'status',
        'checked_in_at',
        'checked_out_at',
        'overtime_minutes',
        'overtime_rate_percent',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'present',
        'overtime_minutes' => 0,
        'overtime_rate_percent' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'work_date' => 'date',
            'status' => AttendanceStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'overtime_minutes' => 'integer',
            'overtime_rate_percent' => 'integer',
        ];
    }

    /**
     * Employee this attendance belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @param  Builder<Attendance>  $query
     * @param  array{
     *     employee_id?: int|null,
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $params
     * @return Builder<Attendance>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['employee_id'] ?? null, function (Builder $query, int $employeeId): void {
                $query->where('employee_id', $employeeId);
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['from'] ?? null, function (Builder $query, string $from): void {
                $query->whereDate('work_date', '>=', $from);
            })
            ->when($params['to'] ?? null, function (Builder $query, string $to): void {
                $query->whereDate('work_date', '<=', $to);
            });
    }

    /**
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['work_date', 'status', 'checked_in_at', 'created_at', 'id'];
        $sort = $sort ?: '-work_date';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'work_date';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
