<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\FlashSaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Flash sale line for a product or variant.
 *
 * @property int $id
 * @property int $flash_sale_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property string $sale_price
 * @property int|null $qty_limit
 * @property int $sold_qty
 * @property int|null $per_customer_limit
 * @property int|null $customer_group_id
 * @property int|null $customer_segment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashSaleItem extends Model
{
    /** @use HasFactory<FlashSaleItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'product_variant_id',
        'sale_price',
        'qty_limit',
        'sold_qty',
        'per_customer_limit',
        'customer_group_id',
        'customer_segment_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sold_qty' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'qty_limit' => 'integer',
            'sold_qty' => 'integer',
            'per_customer_limit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FlashSale, $this>
     */
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
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
     * @return BelongsTo<CustomerGroup, $this>
     */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * @return BelongsTo<CustomerSegment, $this>
     */
    public function customerSegment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class);
    }

    /**
     * Remaining sale units when a qty limit is set.
     */
    public function remainingQuantity(): ?int
    {
        if ($this->qty_limit === null) {
            return null;
        }

        return max(0, $this->qty_limit - $this->sold_qty);
    }

    /**
     * Whether the item has hit its global qty limit.
     */
    public function isSoldOut(): bool
    {
        return $this->qty_limit !== null && $this->sold_qty >= $this->qty_limit;
    }
}
