<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Price record for a product or variant.
 *
 * @property int $id
 * @property string $priceable_type
 * @property int $priceable_id
 * @property string $currency
 * @property string $amount
 * @property string|null $compare_at_amount
 * @property string|null $cost_amount
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class ProductPrice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'currency',
        'amount',
        'compare_at_amount',
        'cost_amount',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priceable_id' => 'integer',
            'amount' => 'decimal:2',
            'compare_at_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
