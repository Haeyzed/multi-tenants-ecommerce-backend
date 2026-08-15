<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item within a shopping cart.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $seller_offer_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property array<string, mixed>|null $metadata
 */
class CartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'seller_offer_id',
        'quantity',
        'unit_price',
        'subtotal',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'seller_offer_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<SellerOffer, $this>
     */
    public function sellerOffer(): BelongsTo
    {
        return $this->belongsTo(SellerOffer::class);
    }
}
