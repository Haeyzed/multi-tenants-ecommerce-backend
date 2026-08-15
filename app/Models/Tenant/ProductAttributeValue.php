<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Selectable value for a tenant-global product attribute.
 *
 * @property int $id
 * @property int $product_attribute_id
 * @property string $value
 * @property int $sort_order
 */
class ProductAttributeValue extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_attribute_id',
        'value',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_attribute_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Parent attribute definition.
     *
     * @return BelongsTo<ProductAttribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    /**
     * Products assigned this attribute value.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_attribute_product',
            'product_attribute_value_id',
            'product_id',
        )->withTimestamps();
    }
}
