<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Enums\Notification\DeliveryStatus;
use App\Enums\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Audit record for a channel delivery attempt.
 *
 * Uses the active database connection (central or tenant).
 *
 * @property int $id
 * @property string $notifiable_type
 * @property int|string $notifiable_id
 * @property string $notification_key
 * @property NotificationChannel $channel
 * @property DeliveryStatus $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
class NotificationDelivery extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'notification_key',
        'channel',
        'status',
        'provider',
        'provider_message_id',
        'error',
        'metadata',
        'sent_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DeliveryStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
