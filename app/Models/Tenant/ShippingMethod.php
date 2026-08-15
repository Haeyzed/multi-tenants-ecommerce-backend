<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Available shipping option for checkout and orders.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $amount
 * @property string|null $min_order_amount
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $estimated_days_min
 * @property int|null $estimated_days_max
 */
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'amount',
        'min_order_amount',
        'is_active',
        'sort_order',
        'estimated_days_min',
        'estimated_days_max',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
}
