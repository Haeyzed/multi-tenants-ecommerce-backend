<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Meetings;

use App\Contracts\Interview\InterviewMeetingProvider;
use App\Exceptions\Interview\UnsupportedInterviewMeetingProviderException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves interview meeting providers from config. Adding a driver is a class + config entry.
 */
class InterviewMeetingManager
{
    /**
     * Create a new class instance.
     *
     * @param  Container  $container
     */
    public function __construct(private readonly Container $container) {}

    /**
     * Driver.
     *
     * @param  ?string  $name
     * @return InterviewMeetingProvider
     */
    public function driver(?string $name = null): InterviewMeetingProvider
    {
        $name ??= (string) config('interview_meetings.default', 'manual');
        $class = config('interview_meetings.drivers.'.$name);

        if (! is_string($class) || $class === '' || ! is_a($class, InterviewMeetingProvider::class, true)) {
            throw new UnsupportedInterviewMeetingProviderException($name);
        }

        /** @var InterviewMeetingProvider $provider */
        $provider = $this->container->make($class);

        return $provider;
    }

    /**
     * Drivers.
     *
     * @return list<string>
     */
    public function drivers(): array
    {
        /** @var array<string, mixed> $drivers */
        $drivers = config('interview_meetings.drivers', []);

        $names = array_keys($drivers);

        if (! app()->environment('testing', 'local')) {
            $names = array_values(array_filter(
                $names,
                static fn (string $name): bool => $name !== 'fake',
            ));
        }

        return array_values($names);
    }
}
