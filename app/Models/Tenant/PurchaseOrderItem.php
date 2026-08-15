<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Line item on a purchase order.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $received_quantity
 * @property string $unit_cost
 * @property string $tax
 * @property string $total
 */
class PurchaseOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'received_quantity',
        'unit_cost',
        'tax',
        'total',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'received_quantity' => 0,
        'tax' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * @return HasMany<GoodsReceiptItem, $this>
     */
    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
