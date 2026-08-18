<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\PayFrequency;
use Database\Factories\Tenant\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Current compensation configuration for an employee.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $base_salary
 * @property string $currency
 * @property PayFrequency $pay_frequency
 * @property Carbon $effective_from
 */
class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'base_salary',
        'currency',
        'pay_frequency',
        'effective_from',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'NGN',
        'pay_frequency' => 'monthly',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'base_salary' => 'decimal:2',
            'pay_frequency' => PayFrequency::class,
            'effective_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
