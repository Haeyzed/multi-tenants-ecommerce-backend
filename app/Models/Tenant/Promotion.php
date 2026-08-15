<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\PromotionType;
use Database\Factories\Tenant\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Automatic cart promotion rule.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property PromotionType $type
 * @property string $value
 * @property string $min_order_amount
 * @property string|null $max_discount
 * @property int $priority
 * @property bool $is_exclusive
 * @property bool $is_stackable
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property array<string, mixed>|null $metadata
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount',
        'priority',
        'is_exclusive',
        'is_stackable',
        'is_active',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'value' => 0,
        'min_order_amount' => 0,
        'priority' => 0,
        'is_exclusive' => false,
        'is_stackable' => true,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'priority' => 'integer',
            'is_exclusive' => 'boolean',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_category')->withTimestamps();
    }
}
