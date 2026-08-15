<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Notification\NotificationPreference;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves and updates per-user notification channel preferences.
 */
class NotificationPreferenceService
{
    /**
     * List effective preferences for a user across known templates.
     *
     * @return list<array{
     *     notification_key: string,
     *     name: string,
     *     is_mandatory: bool,
     *     channels: list<array{channel: string, enabled: bool, locked: bool}>
     * }>
     */
    public function listForUser(Model $user): array
    {
        $templates = NotificationTemplate::query()
            ->where('is_active', true)
            ->orderBy('key')
            ->get();

        $stored = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->groupBy('notification_key');

        $result = [];

        foreach ($templates as $template) {
            /** @var list<string> $channels */
            $channels = $template->channels ?? [];
            $channelPrefs = [];

            foreach ($channels as $channel) {
                $locked = $template->is_mandatory
                    && in_array($channel, [NotificationChannel::Database->value, NotificationChannel::Email->value], true);

                $preference = ($stored->get($template->key) ?? collect())
                    ->firstWhere('channel', NotificationChannel::tryFrom($channel) ?? $channel);

                $enabled = $locked
                    ? true
                    : ($preference?->enabled ?? true);

                $channelPrefs[] = [
                    'channel' => $channel,
                    'enabled' => (bool) $enabled,
                    'locked' => $locked,
                ];
            }

            $result[] = [
                'notification_key' => $template->key,
                'name' => $template->name,
                'is_mandatory' => $template->is_mandatory,
                'channels' => $channelPrefs,
            ];
        }

        return $result;
    }

    /**
     * Upsert preference rows for the user.
     *
     * @param  list<array{notification_key: string, channel: string, enabled: bool}>  $preferences
     * @return list<array{
     *     notification_key: string,
     *     name: string,
     *     is_mandatory: bool,
     *     channels: list<array{channel: string, enabled: bool, locked: bool}>
     * }>
     */
    public function syncForUser(Model $user, array $preferences): array
    {
        foreach ($preferences as $preference) {
            $template = NotificationTemplate::query()
                ->where('key', $preference['notification_key'])
                ->first();

            if ($template === null) {
                continue;
            }

            $channel = NotificationChannel::from($preference['channel']);
            $locked = $template->is_mandatory
                && in_array($channel, [NotificationChannel::Database, NotificationChannel::Email], true);

            $enabled = $locked ? true : (bool) $preference['enabled'];

            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'notification_key' => $template->key,
                    'channel' => $channel,
                ],
                ['enabled' => $enabled],
            );
        }

        return $this->listForUser($user);
    }

    /**
     * Whether the user allows the channel for the given notification key.
     */
    public function isEnabled(Model $user, string $notificationKey, NotificationChannel $channel, bool $isMandatory = false): bool
    {
        if ($isMandatory && in_array($channel, [NotificationChannel::Database, NotificationChannel::Email], true)) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled ?? true;
    }
}
