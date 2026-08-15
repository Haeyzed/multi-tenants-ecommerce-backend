<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Models\Concerns\HasSeo;
use Database\Factories\Tenant\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Tenant catalog product.
 *
 * @property int $id
 * @property int|null $brand_id
 * @property int|null $unit_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property ProductType $type
 * @property ProductStatus $status
 * @property ProductVisibility $visibility
 * @property bool $has_variants
 * @property bool $is_featured
 * @property bool $allow_backorder
 * @property bool $is_preorder
 * @property Carbon|null $preorder_start_at
 * @property Carbon|null $preorder_end_at
 * @property int|null $minimum_purchase_quantity
 * @property int|null $maximum_purchase_quantity
 * @property string|null $average_rating
 * @property int $reviews_count
 * @property string|null $tax_class
 * @property string|null $weight
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property Carbon|null $published_at
 * @property Carbon|null $unpublished_at
 * @property int $sort_order
 */
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSeo, HasSlug, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'unit_id',
        'name',
        'slug',
        'description',
        'short_description',
        'type',
        'status',
        'visibility',
        'has_variants',
        'is_featured',
        'allow_backorder',
        'is_preorder',
        'preorder_start_at',
        'preorder_end_at',
        'minimum_purchase_quantity',
        'maximum_purchase_quantity',
        'average_rating',
        'reviews_count',
        'tax_class',
        'weight',
        'length',
        'width',
        'height',
        'published_at',
        'unpublished_at',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'allow_backorder' => false,
        'is_preorder' => false,
        'reviews_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brand_id' => 'integer',
            'unit_id' => 'integer',
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'visibility' => ProductVisibility::class,
            'has_variants' => 'boolean',
            'is_featured' => 'boolean',
            'allow_backorder' => 'boolean',
            'is_preorder' => 'boolean',
            'preorder_start_at' => 'datetime',
            'preorder_end_at' => 'datetime',
            'minimum_purchase_quantity' => 'integer',
            'maximum_purchase_quantity' => 'integer',
            'average_rating' => 'decimal:2',
            'reviews_count' => 'integer',
            'weight' => 'decimal:3',
            'length' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the product name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Register the multi-image product gallery and OG image collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Images->value)
            ->acceptsMimeTypes(config('media.mimes.image', []));

        $this->addMediaCollection(MediaCollection::OgImage->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    /**
     * Register image conversions for product images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');
        $small = config('media.conversions.small');
        $medium = config('media.conversions.medium');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Images->value, MediaCollection::OgImage->value);

        $this->addMediaConversion(MediaConversion::Small->value)
            ->fit(Fit::Max, (int) $small['width'], (int) $small['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Images->value);

        $this->addMediaConversion(MediaConversion::Medium->value)
            ->fit(Fit::Max, (int) $medium['width'], (int) $medium['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Images->value);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Direct category assignments (not including parent categories).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product')->withTimestamps();
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Assigned attribute values for this product.
     *
     * @return BelongsToMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'product_attribute_product',
            'product_id',
            'product_attribute_value_id',
        )->withTimestamps();
    }

    /**
     * @return MorphMany<ProductPrice, $this>
     */
    public function prices(): MorphMany
    {
        return $this->morphMany(ProductPrice::class, 'priceable');
    }

    /**
     * @return MorphMany<Inventory, $this>
     */
    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }

    /**
     * Outgoing catalog relations (related / upsell / cross-sell rows).
     *
     * Named productRelations to avoid clashing with Eloquent's $relations.
     *
     * @return HasMany<ProductRelation, $this>
     */
    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Related products (type = related).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->relatedByType(ProductRelationType::Related);
    }

    /**
     * Upsell products.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function upsells(): BelongsToMany
    {
        return $this->relatedByType(ProductRelationType::Upsell);
    }

    /**
     * Cross-sell products.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function crossSells(): BelongsToMany
    {
        return $this->relatedByType(ProductRelationType::CrossSell);
    }

    /**
     * Collections this product belongs to.
     *
     * @return BelongsToMany<ProductCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Tags assigned to this product.
     *
     * @return BelongsToMany<ProductTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag')->withTimestamps();
    }

    /**
     * Badges assigned to this product.
     *
     * @return BelongsToMany<ProductBadge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(ProductBadge::class, 'product_product_badge')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Specification rows for this product.
     *
     * @return HasMany<ProductSpecification, $this>
     */
    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Customer reviews for this product.
     *
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Bundle line items when this product is a bundle.
     *
     * @return HasMany<ProductBundleItem, $this>
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Shared belongsToMany for typed product relations.
     *
     * @return BelongsToMany<Product, $this>
     */
    protected function relatedByType(ProductRelationType $type): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_relations',
            'product_id',
            'related_product_id',
        )
            ->withPivot(['type', 'sort_order'])
            ->wherePivot('type', $type->value)
            ->orderByPivot('sort_order');
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     search?: string|null,
     *     brand_id?: int|null,
     *     status?: ProductStatus|string|null,
     *     type?: ProductType|string|null,
     *     visibility?: ProductVisibility|string|null,
     *     is_featured?: bool|null
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
            ->when(array_key_exists('brand_id', $params) && $params['brand_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('brand_id', (int) $params['brand_id']);
            })
            ->when($params['status'] ?? null, function (Builder $query, ProductStatus|string $status): void {
                $query->where('status', $status instanceof ProductStatus ? $status->value : $status);
            })
            ->when($params['type'] ?? null, function (Builder $query, ProductType|string $type): void {
                $query->where('type', $type instanceof ProductType ? $type->value : $type);
            })
            ->when($params['visibility'] ?? null, function (Builder $query, ProductVisibility|string $visibility): void {
                $query->where('visibility', $visibility instanceof ProductVisibility ? $visibility->value : $visibility);
            })
            ->when(array_key_exists('is_featured', $params) && $params['is_featured'] !== null, function (Builder $query) use ($params): void {
                $query->where('is_featured', (bool) $params['is_featured']);
            })
            ->when(array_key_exists('tag_id', $params) && $params['tag_id'] !== null, function (Builder $query) use ($params): void {
                $query->whereHas('tags', fn (Builder $query) => $query->where('product_tags.id', (int) $params['tag_id']));
            })
            ->when(array_key_exists('collection_id', $params) && $params['collection_id'] !== null, function (Builder $query) use ($params): void {
                $query->whereHas('collections', fn (Builder $query) => $query->where('collections.id', (int) $params['collection_id']));
            });
    }

    /**
     * Products that are active, public, and within the publish window.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', ProductStatus::Active)
            ->where('visibility', ProductVisibility::Public)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('unpublished_at')->orWhere('unpublished_at', '>', $now);
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
        $allowed = ['name', 'slug', 'sort_order', 'published_at', 'created_at', 'updated_at', 'average_rating', 'id'];
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
