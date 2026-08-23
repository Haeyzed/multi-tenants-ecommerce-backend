<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Enums\Tenant\HR\PayrollLineType;
use App\Enums\Tenant\HR\SalaryComponentCalculation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurring earning, deduction, or tax line on an employee salary.
 *
 * @property int $id
 * @property int $employee_salary_id
 * @property PayrollLineType $type
 * @property SalaryComponentCalculation $calculation
 * @property string $code
 * @property string $label
 * @property string $amount
 * @property bool $is_tax
 * @property int $sort_order
 */
class EmployeeSalaryComponent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_salary_id',
        'type',
        'calculation',
        'code',
        'label',
        'amount',
        'is_tax',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'calculation' => 'fixed',
        'is_tax' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_salary_id' => 'integer',
            'type' => PayrollLineType::class,
            'calculation' => SalaryComponentCalculation::class,
            'amount' => 'decimal:2',
            'is_tax' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmployeeSalary, $this>
     */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }
}
