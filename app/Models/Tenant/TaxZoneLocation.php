<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nnjeim World location mapping for a tax zone.
 *
 * @property int $id
 * @property int $tax_zone_id
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $city_id
 */
class TaxZoneLocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tax_zone_id',
        'country_id',
        'state_id',
        'city_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_zone_id' => 'integer',
            'country_id' => 'integer',
            'state_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class);
    }
}
