<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Tax\TaxAppliesTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a tax to a zone with an applicability scope.
 *
 * @property int $id
 * @property int $tax_id
 * @property int $tax_zone_id
 * @property TaxAppliesTo $applies_to
 * @property bool $is_active
 */
class TaxRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tax_id',
        'tax_zone_id',
        'applies_to',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_id' => 'integer',
            'tax_zone_id' => 'integer',
            'applies_to' => TaxAppliesTo::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class);
    }
}
