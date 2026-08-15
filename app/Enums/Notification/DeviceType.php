<?php

declare(strict_types=1);

namespace App\Enums\Notification;

/**
 * Device platforms that can receive push notifications.
 */
enum DeviceType: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';
}
