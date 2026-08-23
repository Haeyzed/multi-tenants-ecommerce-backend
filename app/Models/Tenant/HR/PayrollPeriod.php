<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Enums\Tenant\HR\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Planned payroll window derived from HR settings.
 */
class PayrollPeriod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_start',
        'period_end',
        'payment_date',
        'frequency',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payment_date' => 'date',
            'frequency' => PayFrequency::class,
            'status' => PayrollPeriodStatus::class,
        ];
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     * @return Builder<PayrollPeriod>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', PayrollPeriodStatus::Open)
            ->orderBy('period_start')
            ->orderBy('id');
    }
}
