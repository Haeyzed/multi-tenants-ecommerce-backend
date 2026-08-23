<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Annual leave entitlement for an employee and leave type.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property int $entitled
 * @property int $carried_over
 * @property int $used
 */
class LeaveBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'entitled',
        'carried_over',
        'used',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'entitled' => 0,
        'carried_over' => 0,
        'used' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'year' => 'integer',
            'entitled' => 'integer',
            'carried_over' => 'integer',
            'used' => 'integer',
        ];
    }

    /**
     * Remaining days that may still be requested.
     */
    public function remaining(): int
    {
        return max(0, ($this->entitled + $this->carried_over) - $this->used);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
