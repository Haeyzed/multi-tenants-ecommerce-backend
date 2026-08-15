<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Commerce\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Order invoice with optional PDF document.
 *
 * @property int $id
 * @property int $order_id
 * @property int $customer_id
 * @property string $invoice_number
 * @property InvoiceStatus $status
 * @property string $currency
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $shipping_total
 * @property string $grand_total
 * @property Carbon|null $issued_at
 * @property array<string, mixed>|null $metadata
 */
class Invoice extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'invoice_number',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'issued_at',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Documents->value)
            ->singleFile();
    }
}
