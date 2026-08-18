<?php

declare(strict_types=1);

namespace App\Exceptions\Interview;

use InvalidArgumentException;

/**
 * Thrown when a meeting provider driver is not registered in config.
 */
class UnsupportedInterviewMeetingProviderException extends InvalidArgumentException
{
    public function __construct(string $name)
    {
        /** @var array<string, mixed> $drivers */
        $drivers = config('interview_meetings.drivers', []);
        $known = implode(', ', array_keys($drivers));

        parent::__construct(
            "Unsupported interview meeting provider [{$name}]. Known drivers: {$known}."
        );
    }
}
