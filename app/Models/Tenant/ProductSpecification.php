<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Key/value specification row for a product.
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $group
 * @property string $name
 * @property string $value
 * @property int $sort_order
 */
class ProductSpecification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'group',
        'name',
        'value',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
