<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\CheckoutSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Checkout attempt linked to a customer cart.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $cart_id
 * @property string|null $idempotency_key
 * @property CheckoutSessionStatus $status
 * @property string $currency
 * @property int|null $shipping_address_id
 * @property int|null $billing_address_id
 * @property int|null $shipping_method_id
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $shipping_total
 * @property string $grand_total
 * @property Carbon|null $expires_at
 * @property int|null $order_id
 */
class CheckoutSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'cart_id',
        'idempotency_key',
        'status',
        'currency',
        'shipping_address_id',
        'billing_address_id',
        'shipping_method_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'expires_at',
        'order_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'cart_id' => 'integer',
            'status' => CheckoutSessionStatus::class,
            'shipping_address_id' => 'integer',
            'billing_address_id' => 'integer',
            'shipping_method_id' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'expires_at' => 'datetime',
            'order_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    /**
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'billing_address_id');
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
