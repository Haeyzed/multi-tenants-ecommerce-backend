<?php

declare(strict_types=1);

namespace App\DTO\Interview;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Normalized meeting operation input. Credentials are tenant-scoped and must not be logged.
 */
readonly class MeetingRequest
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public string $topic,
        public DateTimeInterface $startsAt,
        public int $durationMinutes,
        public string $timezone,
        public ?string $joinUrl = null,
        public ?string $password = null,
        public ?string $externalId = null,
        public array $credentials = [],
        public ?int $interviewId = null,
        public ?string $agenda = null,
    ) {}

    public function endsAt(): DateTimeInterface
    {
        return Carbon::instance(Carbon::parse($this->startsAt))
            ->addMinutes($this->durationMinutes);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        $value = $this->credentials[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function requiredCredential(string $key): string
    {
        $value = $this->credential($key);

        return is_string($value) ? $value : '';
    }
}
