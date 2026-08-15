<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable tax definition.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property bool $is_inclusive
 * @property int $priority
 */
class Tax extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_inclusive',
        'priority',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_inclusive' => false,
        'priority' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_inclusive' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
