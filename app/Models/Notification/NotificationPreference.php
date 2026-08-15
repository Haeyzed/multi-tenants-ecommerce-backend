<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user channel preference for a notification template key.
 *
 * Uses the active database connection (central or tenant).
 *
 * @property int $id
 * @property int $user_id
 * @property string $notification_key
 * @property NotificationChannel $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'notification_key',
        'channel',
        'enabled',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'enabled' => 'boolean',
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
     * Resolve the user model class for the current connection context.
     *
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return tenancy()->initialized
            ? User::class
            : \App\Models\Landlord\User::class;
    }
}
