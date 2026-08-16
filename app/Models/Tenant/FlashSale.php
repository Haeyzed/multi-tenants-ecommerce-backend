<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\FlashSaleStatus;
use Database\Factories\Tenant\FlashSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Time-boxed product flash sale (distinct from promotions/coupons).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property bool $stack_with_coupons
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashSale extends Model
{
    /** @use HasFactory<FlashSaleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'is_active',
        'stack_with_coupons',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'stack_with_coupons' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'stack_with_coupons' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FlashSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    /**
     * Computed schedule status (not stored).
     */
    public function status(): FlashSaleStatus
    {
        if (! $this->is_active) {
            return FlashSaleStatus::Inactive;
        }

        $now = now();

        if ($this->starts_at->isFuture()) {
            return FlashSaleStatus::Scheduled;
        }

        if ($this->ends_at->isPast()) {
            return FlashSaleStatus::Ended;
        }

        return FlashSaleStatus::Active;
    }

    /**
     * Whether the sale is currently purchasable.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->status() === FlashSaleStatus::Active;
    }
}
