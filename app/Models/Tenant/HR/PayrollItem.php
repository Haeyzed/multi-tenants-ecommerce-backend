<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use Database\Factories\HR\PayrollItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee payslip within a payroll run.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $base_salary
 * @property string $gross_pay
 * @property string $deduction_total
 * @property string $net_pay
 * @property int $working_days
 * @property int $absent_days
 * @property int $unpaid_leave_days
 * @property int $overtime_minutes
 * @property int $scheduled_days
 * @property string|null $bank_name
 * @property string|null $bank_code
 * @property string|null $account_number
 * @property string|null $account_name
 * @property string|null $notes
 */
class PayrollItem extends Model
{
    /** @use HasFactory<PayrollItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'base_salary',
        'gross_pay',
        'deduction_total',
        'net_pay',
        'working_days',
        'absent_days',
        'unpaid_leave_days',
        'overtime_minutes',
        'scheduled_days',
        'notes',
        'ytd_gross',
        'ytd_paye',
        'employer_pension',
        'employer_nsitf',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'deduction_total' => '0.00',
        'working_days' => 0,
        'absent_days' => 0,
        'unpaid_leave_days' => 0,
        'overtime_minutes' => 0,
        'scheduled_days' => 0,
        'ytd_gross' => '0.00',
        'ytd_paye' => '0.00',
        'employer_pension' => '0.00',
        'employer_nsitf' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payroll_run_id' => 'integer',
            'employee_id' => 'integer',
            'working_days' => 'integer',
            'absent_days' => 'integer',
            'unpaid_leave_days' => 'integer',
            'overtime_minutes' => 'integer',
            'scheduled_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollItemLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class);
    }
}
