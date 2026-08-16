<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\CustomerSegmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Rule-driven customer segment evaluated on the fly against customer data.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array{match?: string, conditions?: list<array{type: string, value?: mixed}>}|null $rules
 * @property bool $is_active
 * @property int $sort_order
 */
class CustomerSegment extends Model
{
    /** @use HasFactory<CustomerSegmentFactory> */
    use HasFactory, HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'rules',
        'is_active',
        'sort_order',
        'customers_count',
        'membership_refreshed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'customers_count' => 'integer',
            'membership_refreshed_at' => 'datetime',
        ];
    }

    /**
     * Materialized members of this segment.
     *
     * @return HasMany<CustomerSegmentMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CustomerSegmentMember::class);
    }

    /**
     * Configure slug generation from the segment name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Rule conditions that must be evaluated for this segment.
     *
     * @return list<array{type: string, value?: mixed}>
     */
    public function conditions(): array
    {
        $conditions = $this->rules['conditions'] ?? [];

        return array_values(array_filter(
            $conditions,
            fn (mixed $condition): bool => is_array($condition) && isset($condition['type']),
        ));
    }

    /**
     * Whether every condition must match (`all`) or any single one (`any`).
     */
    public function matchMode(): string
    {
        return ($this->rules['match'] ?? 'all') === 'any' ? 'any' : 'all';
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
            })
            ->when(array_key_exists('is_active', $params) && $params['is_active'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_active', (bool) $params['is_active']);
            });
    }

    /**
     * Apply a whitelist of sorts.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'slug', 'sort_order', 'created_at', 'updated_at', 'id'];
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
