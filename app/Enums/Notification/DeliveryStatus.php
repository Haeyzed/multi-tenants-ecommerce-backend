<?php

declare(strict_types=1);

namespace App\Enums\Notification;

/**
 * Delivery attempt outcome for a notification channel send.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
