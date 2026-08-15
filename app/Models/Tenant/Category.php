<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Models\Concerns\HasSeo;
use Database\Factories\Tenant\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Tenant catalog category with optional parent hierarchy.
 *
 * Category belongsToMany Products via category_product (direct products only).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSeo, HasSlug, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the category name.
     *
     * Slugs stay stable after first save (SEO-friendly).
     * Uniqueness is tenant-wide; Spatie appends suffixes on collision.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Register the single-file category image and OG image collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Image->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));

        $this->addMediaCollection(MediaCollection::OgImage->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    /**
     * Register image conversions for category images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');
        $small = config('media.conversions.small');
        $medium = config('media.conversions.medium');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Image->value, MediaCollection::OgImage->value);

        $this->addMediaConversion(MediaConversion::Small->value)
            ->fit(Fit::Max, (int) $small['width'], (int) $small['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Image->value);

        $this->addMediaConversion(MediaConversion::Medium->value)
            ->fit(Fit::Max, (int) $medium['width'], (int) $medium['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Image->value);
    }

    /**
     * Products directly assigned to this category.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')->withTimestamps();
    }

    /**
     * Parent category (null for root categories).
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Immediate child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Recursive children for tree endpoints.
     *
     * @return HasMany<Category, $this>
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     parent_id?: int|null,
     *     root?: bool|null
     * }  $params
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
            })
            ->when(array_key_exists('parent_id', $params) && $params['parent_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('parent_id', (int) $params['parent_id']);
            })
            ->when(! empty($params['root']), function (Builder $query): void {
                $query->whereNull('parent_id');
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
