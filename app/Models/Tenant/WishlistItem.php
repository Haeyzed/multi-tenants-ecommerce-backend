<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product saved to a customer wishlist.
 *
 * @property int $id
 * @property int $wishlist_id
 * @property int $product_id
 * @property int|null $product_variant_id
 */
class WishlistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'wishlist_id',
        'product_id',
        'product_variant_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wishlist_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
