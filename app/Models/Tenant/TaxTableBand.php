<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progressive PAYE band on a tax table.
 *
 * @property int $id
 * @property int $tax_table_id
 * @property int $sort_order
 * @property string $min_amount
 * @property string|null $max_amount
 * @property string $rate_percent
 */
class TaxTableBand extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tax_table_id',
        'sort_order',
        'min_amount',
        'max_amount',
        'rate_percent',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'min_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_table_id' => 'integer',
            'sort_order' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TaxTable, $this>
     */
    public function taxTable(): BelongsTo
    {
        return $this->belongsTo(TaxTable::class);
    }
}
