<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Marketplace\CommissionType;
use Database\Factories\Tenant\SellerGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Stable seller classification with optional commission overrides.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CommissionType|null $commission_type
 * @property string|null $commission_rate
 * @property string|null $commission_fixed_amount
 * @property bool $is_active
 * @property int $sort_order
 */
class SellerGroup extends Model
{
    /** @use HasFactory<SellerGroupFactory> */
    use HasFactory, HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'commission_type',
        'commission_rate',
        'commission_fixed_amount',
        'is_active',
        'sort_order',
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
            'commission_type' => CommissionType::class,
            'commission_rate' => 'decimal:4',
            'commission_fixed_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the group name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Sellers assigned to this group.
     *
     * @return HasMany<Seller, $this>
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
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
