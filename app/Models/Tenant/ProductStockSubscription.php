<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Customer subscription for back-in-stock notifications.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property Carbon|null $notified_at
 */
class ProductStockSubscription extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'product_id',
        'product_variant_id',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'notified_at' => 'datetime',
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
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
