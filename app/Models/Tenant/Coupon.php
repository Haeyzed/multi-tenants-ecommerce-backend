<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\CouponType;
use Database\Factories\Tenant\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Discount coupon redeemable at checkout.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property CouponType $type
 * @property string $value
 * @property string $minimum_order_amount
 * @property string|null $maximum_discount
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_customer
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Free unique `code` values on soft delete so codes can be reissued.
     */
    protected static function booted(): void
    {
        static::deleting(function (Coupon $coupon): void {
            if ($coupon->isForceDeleting()) {
                return;
            }

            $suffix = '__d'.$coupon->id;
            $maxBase = max(1, 50 - strlen($suffix));
            $coupon->code = substr($coupon->code, 0, $maxBase).$suffix;
            $coupon->saveQuietly();
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount',
        'usage_limit',
        'usage_limit_per_customer',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'minimum_order_amount' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category')->withTimestamps();
    }
}
