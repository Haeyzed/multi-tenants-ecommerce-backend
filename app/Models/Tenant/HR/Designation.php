<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use Database\Factories\HR\DesignationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant HR job title / designation.
 *
 * @property int $id
 * @property int|null $department_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property bool $is_active
 */
class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Optional department this designation belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Employees assigned this designation.
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @param  Builder<Designation>  $query
     * @param  array{search?: string|null, department_id?: int|null, is_active?: bool|null}  $params
     * @return Builder<Designation>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($params['department_id'] ?? null, function (Builder $query, int $departmentId): void {
                $query->where('department_id', $departmentId);
            })
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            });
    }

    /**
     * @param  Builder<Designation>  $query
     * @return Builder<Designation>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'code', 'is_active', 'created_at', 'updated_at', 'id'];
        $sort = $sort ?: 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'name';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
