<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Geographic zone for tax rules.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class TaxZone extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TaxZoneLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(TaxZoneLocation::class);
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
