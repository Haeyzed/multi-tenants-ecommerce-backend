<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Catalog\ProductRelationType;
use Database\Factories\Tenant\ProductRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Directed catalog relationship between two products (related, upsell, cross-sell).
 *
 * @property int $id
 * @property int $product_id
 * @property int $related_product_id
 * @property ProductRelationType $type
 * @property int $sort_order
 */
class ProductRelation extends Model
{
    /** @use HasFactory<ProductRelationFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'related_product_id',
        'type',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'related_product_id' => 'integer',
            'type' => ProductRelationType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Source product that owns this relation.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Target related product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
