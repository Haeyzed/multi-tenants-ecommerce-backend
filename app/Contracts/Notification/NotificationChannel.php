<?php

declare(strict_types=1);

namespace App\Contracts\Notification;

use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\NotificationChannel as NotificationChannelEnum;
use App\Models\Notification\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a notification delivery channel.
 */
interface NotificationChannel
{
    /**
     * Channel identifier.
     */
    public function name(): NotificationChannelEnum;

    /**
     * Deliver the rendered payload to the notifiable.
     */
    public function send(Model $notifiable, NotificationPayload $payload): NotificationDelivery;
}
