<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods receipt against a purchase order.
 *
 * @property int $id
 * @property string $receipt_number
 * @property int $purchase_order_id
 * @property int $warehouse_id
 * @property Carbon $received_at
 * @property string|null $notes
 * @property int|null $received_by
 */
class GoodsReceipt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'warehouse_id',
        'received_at',
        'notes',
        'received_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'warehouse_id' => 'integer',
            'received_at' => 'datetime',
            'received_by' => 'integer',
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Tenant user who recorded the receipt.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<GoodsReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
