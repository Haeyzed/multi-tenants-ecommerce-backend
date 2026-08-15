<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Purchase order issued to a supplier.
 *
 * @property int $id
 * @property string $order_number
 * @property int $supplier_id
 * @property int $warehouse_id
 * @property string $currency
 * @property PurchaseOrderStatus $status
 * @property string $subtotal
 * @property string $tax_total
 * @property string $discount_total
 * @property string $shipping_total
 * @property string $grand_total
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_at
 * @property string|null $notes
 */
class PurchaseOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'supplier_id',
        'warehouse_id',
        'currency',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'shipping_total',
        'grand_total',
        'ordered_at',
        'expected_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_id' => 'integer',
            'warehouse_id' => 'integer',
            'status' => PurchaseOrderStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'expected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
