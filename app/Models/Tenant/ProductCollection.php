<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\CollectionType;
use App\Models\Concerns\HasSeo;
use Database\Factories\Tenant\ProductCollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Named product collection (curated group of products).
 *
 * Model name avoids clashing with Illuminate\Support\Collection; table is `collections`.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CollectionType $type
 * @property CollectionStatus $status
 * @property int $sort_order
 * @property Carbon|null $published_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class ProductCollection extends Model implements HasMedia
{
    /** @use HasFactory<ProductCollectionFactory> */
    use HasFactory, HasSeo, HasSlug, InteractsWithMedia;

    /**
     * @var string
     */
    protected $table = 'collections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'status',
        'sort_order',
        'published_at',
        'starts_at',
        'ends_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'manual',
        'status' => 'draft',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'status' => CollectionStatus::class,
            'sort_order' => 'integer',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Configure slug generation from the collection name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Register the single-file collection image.
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
     * Register image conversions for collection images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');
        $small = config('media.conversions.small');
        $medium = config('media.conversions.medium');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Image->value);

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
     * Products in this collection.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id', 'product_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     search?: string|null,
     *     status?: CollectionStatus|string|null,
     *     type?: CollectionType|string|null
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
            ->when($params['status'] ?? null, function (Builder $query, CollectionStatus|string $status): void {
                $query->where('status', $status instanceof CollectionStatus ? $status->value : $status);
            })
            ->when($params['type'] ?? null, function (Builder $query, CollectionType|string $type): void {
                $query->where('type', $type instanceof CollectionType ? $type->value : $type);
            });
    }

    /**
     * Collections visible on the public storefront.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', CollectionStatus::Active)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
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
        $allowed = ['name', 'slug', 'sort_order', 'published_at', 'created_at', 'updated_at', 'id'];
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
