<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\User;
use Database\Factories\HR\PayrollRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Batch payroll for a pay period.
 *
 * @property int $id
 * @property int|null $payroll_period_id
 * @property string $reference
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property PayrollRunStatus $status
 * @property string $currency
 * @property string $gross_total
 * @property string $deduction_total
 * @property string $net_total
 * @property int $employee_count
 * @property Carbon|null $processed_at
 * @property Carbon|null $paid_at
 * @property int|null $processed_by
 * @property int|null $paid_by
 * @property string|null $notes
 */
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_period_id',
        'reference',
        'period_start',
        'period_end',
        'status',
        'currency',
        'gross_total',
        'deduction_total',
        'net_total',
        'employee_count',
        'processed_at',
        'paid_at',
        'processed_by',
        'paid_by',
        'notes',
        'nibss_reference',
        'nibss_status',
        'nibss_submitted_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'NGN',
        'gross_total' => '0.00',
        'deduction_total' => '0.00',
        'net_total' => '0.00',
        'employee_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payroll_period_id' => 'integer',
            'status' => PayrollRunStatus::class,
            'employee_count' => 'integer',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
            'processed_by' => 'integer',
            'paid_by' => 'integer',
            'nibss_submitted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Accounting journal entries sourced from this payroll run.
     *
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * @param  Builder<PayrollRun>  $query
     * @param  array{
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null
     * }  $params
     * @return Builder<PayrollRun>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['from'] ?? null, function (Builder $query, string $from): void {
                $query->whereDate('period_end', '>=', $from);
            })
            ->when($params['to'] ?? null, function (Builder $query, string $to): void {
                $query->whereDate('period_start', '<=', $to);
            });
    }

    /**
     * @param  Builder<PayrollRun>  $query
     * @return Builder<PayrollRun>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['period_start', 'period_end', 'status', 'net_total', 'created_at', 'id'];
        $sort = $sort ?: '-period_start';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'period_start';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
