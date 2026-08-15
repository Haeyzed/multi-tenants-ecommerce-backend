<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line on an invoice.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $order_item_id
 * @property string $description
 * @property int $quantity
 * @property string $unit_price
 * @property string $tax_amount
 * @property string $subtotal
 * @property string $total
 */
class InvoiceItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'subtotal',
        'total',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tax_amount' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'order_item_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
