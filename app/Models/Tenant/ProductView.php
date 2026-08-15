<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ProductViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lightweight storefront product view record used for recently viewed and recommendations.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int $product_id
 * @property string|null $session_key
 * @property Carbon $viewed_at
 */
class ProductView extends Model
{
    /** @use HasFactory<ProductViewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'product_id',
        'session_key',
        'viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'viewed_at' => 'datetime',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Limit views to a viewer identified by customer and/or anonymous session key.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForViewer(Builder $query, ?int $customerId, ?string $sessionKey): Builder
    {
        if ($customerId === null && $sessionKey === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($customerId, $sessionKey): void {
            if ($customerId !== null) {
                $query->orWhere('customer_id', $customerId);
            }

            if ($sessionKey !== null) {
                $query->orWhere('session_key', $sessionKey);
            }
        });
    }
}
