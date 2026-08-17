<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\EmploymentStatus;
use Database\Factories\Tenant\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Tenant HR employee profile linked to a staff User (not Authenticatable).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property string|null $job_title
 * @property string|null $employee_number
 * @property EmploymentStatus $employment_status
 * @property Carbon|null $hired_at
 * @property string|null $notes
 */
class Employee extends Model implements HasMedia
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'department_id',
        'designation_id',
        'job_title',
        'employee_number',
        'employment_status',
        'hired_at',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'employment_status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'department_id' => 'integer',
            'designation_id' => 'integer',
            'employment_status' => EmploymentStatus::class,
            'hired_at' => 'date',
        ];
    }

    /**
     * Linked tenant staff user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Department assignment.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Job title assignment.
     *
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Daily attendance records.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Leave requests for this employee.
     *
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Register HR document media for the employee profile.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Documents->value)
            ->acceptsMimeTypes([
                ...config('media.mimes.image', []),
                ...config('media.mimes.document', []),
            ]);
    }

    /**
     * @param  Builder<Employee>  $query
     * @param  array{
     *     search?: string|null,
     *     department_id?: int|null,
     *     designation_id?: int|null,
     *     employment_status?: string|null
     * }  $params
     * @return Builder<Employee>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('job_title', 'like', $like)
                        ->orWhere('employee_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('user', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('designation', function (Builder $query) use ($like): void {
                            $query->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            })
            ->when($params['department_id'] ?? null, function (Builder $query, int $departmentId): void {
                $query->where('department_id', $departmentId);
            })
            ->when($params['designation_id'] ?? null, function (Builder $query, int $designationId): void {
                $query->where('designation_id', $designationId);
            })
            ->when($params['employment_status'] ?? null, function (Builder $query, string $status): void {
                $query->where('employment_status', $status);
            });
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['job_title', 'employee_number', 'employment_status', 'hired_at', 'created_at', 'updated_at', 'id'];
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
