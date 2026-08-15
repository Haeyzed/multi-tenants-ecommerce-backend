<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Enums\Notification\DeviceType;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Push device registration for a user.
 *
 * Uses the active database connection (central or tenant).
 *
 * @property int $id
 * @property int $user_id
 * @property DeviceType $device_type
 * @property string $device_token
 * @property string $provider
 * @property string|null $app_version
 * @property Carbon|null $last_used_at
 * @property bool $is_active
 */
class DeviceToken extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_type',
        'device_token',
        'provider',
        'app_version',
        'last_used_at',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'fcm',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass());
    }

    /**
     * @param  Builder<DeviceToken>  $query
     * @return Builder<DeviceToken>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return tenancy()->initialized
            ? User::class
            : \App\Models\Landlord\User::class;
    }
}
