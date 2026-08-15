<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use Database\Factories\Tenant\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Customer sales order.
 *
 * @property int $id
 * @property string $order_number
 * @property int $customer_id
 * @property string $currency
 * @property OrderStatus $status
 * @property OrderPaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $subtotal
 * @property string $discount_total
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property array<string, mixed>|null $promotion_snapshot
 * @property int|null $loyalty_points_earned
 * @property int|null $loyalty_points_redeemed
 * @property string $tax_total
 * @property array<string, mixed>|null $tax_snapshot
 * @property string $shipping_total
 * @property string $grand_total
 * @property int|null $gift_card_id
 * @property string|null $gift_card_amount
 * @property string|null $store_credit_amount
 * @property int|null $shipping_method_id
 * @property array<string, mixed>|null $shipping_address_snapshot
 * @property array<string, mixed>|null $billing_address_snapshot
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property Carbon $placed_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'customer_id',
        'currency',
        'status',
        'payment_status',
        'fulfillment_status',
        'subtotal',
        'discount_total',
        'coupon_id',
        'coupon_code',
        'promotion_snapshot',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'tax_total',
        'tax_snapshot',
        'shipping_total',
        'grand_total',
        'gift_card_id',
        'gift_card_amount',
        'store_credit_amount',
        'shipping_method_id',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'notes',
        'idempotency_key',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'coupon_id' => 'integer',
            'promotion_snapshot' => 'array',
            'loyalty_points_earned' => 'integer',
            'loyalty_points_redeemed' => 'integer',
            'tax_total' => 'decimal:2',
            'tax_snapshot' => 'array',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'gift_card_id' => 'integer',
            'gift_card_amount' => 'decimal:2',
            'store_credit_amount' => 'decimal:2',
            'shipping_method_id' => 'integer',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Gift card that funded part or all of this order.
     *
     * @return BelongsTo<GiftCard, $this>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<SellerOrder, $this>
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * @return HasMany<OrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<CheckoutSession, $this>
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     payment_status?: string|null,
     *     customer_id?: int|null
     * }  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('order_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhere('idempotency_key', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                $query->where('payment_status', $paymentStatus);
            })
            ->when(array_key_exists('customer_id', $params) && $params['customer_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('customer_id', (int) $params['customer_id']);
            });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['order_number', 'status', 'payment_status', 'grand_total', 'placed_at', 'created_at', 'updated_at', 'id'];
        $sort = $sort ?: '-placed_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'placed_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
