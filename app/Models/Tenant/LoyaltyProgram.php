<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\LoyaltyProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-wide loyalty program settings.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property string $points_per_currency_unit
 * @property int $redemption_points_per_currency
 * @property int $min_redemption_points
 * @property string $max_redemption_percent
 * @property bool $earn_on_order_paid
 */
class LoyaltyProgram extends Model
{
    /** @use HasFactory<LoyaltyProgramFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'points_per_currency_unit',
        'redemption_points_per_currency',
        'min_redemption_points',
        'max_redemption_percent',
        'earn_on_order_paid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'points_per_currency_unit' => 'decimal:2',
            'redemption_points_per_currency' => 'integer',
            'min_redemption_points' => 'integer',
            'max_redemption_percent' => 'decimal:2',
            'earn_on_order_paid' => 'boolean',
        ];
    }
}
