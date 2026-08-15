<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item within a bundle product.
 *
 * @property int $id
 * @property int $bundle_product_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $sort_order
 */
class ProductBundleItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bundle_product_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_product_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Bundle product that contains this item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    /**
     * Included catalog product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Optional specific variant of the included product.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
