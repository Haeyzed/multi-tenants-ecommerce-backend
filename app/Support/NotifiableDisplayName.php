<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolve a human display name for notification recipients across auth models.
 */
final class NotifiableDisplayName
{
    /**
     * Prefer `name`, then first/last, then email.
     */
    public static function resolve(object $notifiable): string
    {
        if (isset($notifiable->name) && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        $composed = trim(($notifiable->first_name ?? '').' '.($notifiable->last_name ?? ''));

        if ($composed !== '') {
            return $composed;
        }

        return (string) ($notifiable->email ?? '');
    }
}
