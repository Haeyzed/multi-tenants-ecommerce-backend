<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Enums\Tenant\HR\EmploymentChangeType;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\EmploymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical employment snapshot for an employee.
 */
class EmploymentRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'change_type',
        'department_id',
        'designation_id',
        'manager_id',
        'job_title',
        'employment_status',
        'employment_type',
        'work_location',
        'effective_on',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'change_type' => EmploymentChangeType::class,
            'department_id' => 'integer',
            'designation_id' => 'integer',
            'manager_id' => 'integer',
            'employment_status' => EmploymentStatus::class,
            'employment_type' => EmploymentType::class,
            'effective_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
