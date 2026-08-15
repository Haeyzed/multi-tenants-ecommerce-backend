<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Selectable value for a tenant-global product option.
 *
 * @property int $id
 * @property int $product_option_id
 * @property string $value
 * @property string $slug
 * @property int $sort_order
 */
class ProductOptionValue extends Model
{
    use HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_option_id',
        'value',
        'slug',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_option_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the display value.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('value')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    /**
     * Parent option definition.
     *
     * @return BelongsTo<ProductOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /**
     * Variants that use this option value.
     *
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_value',
            'product_option_value_id',
            'product_variant_id',
        )->withTimestamps();
    }
}
