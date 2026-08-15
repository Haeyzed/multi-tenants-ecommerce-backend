<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time-bound rate for a tax.
 *
 * @property int $id
 * @property int $tax_id
 * @property string $rate
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
class TaxRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tax_id',
        'rate',
        'effective_from',
        'effective_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_id' => 'integer',
            'rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
