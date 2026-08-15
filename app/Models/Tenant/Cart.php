<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\CartStatus;
use Database\Factories\Tenant\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Customer shopping cart.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $currency
 * @property CartStatus $status
 * @property Carbon|null $expires_at
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'currency',
        'status',
        'expires_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'status' => CartStatus::class,
            'expires_at' => 'datetime',
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
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<CheckoutSession, $this>
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }
}
