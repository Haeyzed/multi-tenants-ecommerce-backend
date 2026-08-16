<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-specific payment gateway credentials and enablement.
 *
 * @property int $id
 * @property string $gateway
 * @property bool $is_enabled
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property int $sort_order
 */
class TenantPaymentGateway extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'gateway',
        'is_enabled',
        'credentials',
        'settings',
        'sort_order',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
