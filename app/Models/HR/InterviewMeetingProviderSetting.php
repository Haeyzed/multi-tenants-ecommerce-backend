<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Enums\Tenant\HR\MeetingProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped meeting provider enablement and encrypted credentials.
 *
 * @property int $id
 * @property string $provider
 * @property bool $enabled
 * @property array<string, mixed>|null $credentials
 */
class InterviewMeetingProviderSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'enabled',
        'credentials',
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
        'enabled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => MeetingProvider::class,
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }
}
