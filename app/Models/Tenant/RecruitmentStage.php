<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\JobApplicationStatus;
use Database\Factories\Tenant\RecruitmentStageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-configurable recruitment pipeline stage.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property JobApplicationStatus $kind
 * @property int $sort_order
 * @property bool $is_default
 * @property bool $is_terminal
 */
class RecruitmentStage extends Model
{
    /** @use HasFactory<RecruitmentStageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'kind',
        'sort_order',
        'is_default',
        'is_terminal',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_default' => false,
        'is_terminal' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => JobApplicationStatus::class,
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_terminal' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @param  Builder<RecruitmentStage>  $query
     * @return Builder<RecruitmentStage>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'sort_order', 'kind', 'created_at', 'id'];
        $sort = $sort ?: 'sort_order';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'sort_order';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
