<?php

declare(strict_types=1);

namespace App\Enums\Notification;

/**
 * Supported notification delivery channels.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Email = 'email';
    case Push = 'push';
    case Sms = 'sms';
}
