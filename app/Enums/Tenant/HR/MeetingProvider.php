<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Registered interview meeting provider drivers.
 */
enum MeetingProvider: string
{
    case Manual = 'manual';
    case Zoom = 'zoom';
    case GoogleMeet = 'google_meet';
    case MicrosoftTeams = 'microsoft_teams';
    case Fake = 'fake';

    public function requiresExternalApi(): bool
    {
        return match ($this) {
            self::Manual, self::Fake => false,
            default => true,
        };
    }

    public function isTestingOnly(): bool
    {
        return $this === self::Fake;
    }

    /**
     * @return list<string>
     */
    public static function publicValues(): array
    {
        return array_values(array_map(
            static fn (self $provider): string => $provider->value,
            array_filter(self::cases(), static fn (self $provider): bool => ! $provider->isTestingOnly()),
        ));
    }
}
