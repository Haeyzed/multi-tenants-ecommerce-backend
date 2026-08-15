<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use Database\Factories\Tenant\SellerOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A seller's commercial offer for a catalogue product (or variant).
 *
 * @property int $id
 * @property int $seller_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property string|null $sku
 * @property string $currency
 * @property string $price
 * @property string|null $compare_at_price
 * @property string|null $cost
 * @property SellerOfferStatus $status
 * @property array<string, mixed>|null $metadata
 */
class SellerOffer extends Model
{
    /** @use HasFactory<SellerOfferFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'product_id',
        'product_variant_id',
        'sku',
        'currency',
        'price',
        'compare_at_price',
        'cost',
        'status',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'status' => SellerOfferStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Whether the offer can be added to a marketplace cart.
     */
    public function isPurchasable(): bool
    {
        return $this->status === SellerOfferStatus::Active
            && $this->seller !== null
            && $this->seller->canSell();
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
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
     * Stock rows for this offer (warehouse-scoped).
     *
     * @return MorphMany<Inventory, $this>
     */
    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }
}
