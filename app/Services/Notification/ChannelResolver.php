<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\Notification\NotificationChannel as NotificationChannelContract;
use App\Enums\Notification\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves concrete channel handlers and capability filters.
 */
class ChannelResolver
{
    /**
     * @param  iterable<NotificationChannelContract>  $channels
     */
    public function __construct(
        private readonly iterable $channels,
        private readonly NotificationPreferenceService $preferences,
        private readonly DeviceTokenService $devices,
    ) {}

    /**
     * @param  list<string>  $templateChannels
     * @return list<NotificationChannelContract>
     */
    public function resolve(Model $notifiable, string $notificationKey, array $templateChannels, bool $isMandatory): array
    {
        $resolved = [];

        foreach ($this->channels as $channel) {
            $name = $channel->name();

            if (! in_array($name->value, $templateChannels, true)) {
                continue;
            }

            if (! $this->preferences->isEnabled($notifiable, $notificationKey, $name, $isMandatory)) {
                continue;
            }

            if ($name === NotificationChannel::Push && ! $this->devices->hasActiveDevices($notifiable)) {
                continue;
            }

            if ($name === NotificationChannel::Sms && blank(data_get($notifiable, 'phone'))) {
                continue;
            }

            $resolved[] = $channel;
        }

        return $resolved;
    }

    /**
     * @return Collection<string, NotificationChannelContract>
     */
    public function all(): Collection
    {
        return collect($this->channels)->keyBy(fn (NotificationChannelContract $channel) => $channel->name()->value);
    }
}
