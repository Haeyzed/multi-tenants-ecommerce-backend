<?php

declare(strict_types=1);

namespace App\DTO\Interview;

use DateTimeInterface;

/**
 * Provider-agnostic meeting payload persisted against an InterviewMeeting.
 */
readonly class MeetingResult
{
    public function __construct(
        public string $provider,
        public string $status,
        public ?string $externalId = null,
        public ?string $joinUrl = null,
        public ?string $hostUrl = null,
        public ?string $password = null,
        public ?DateTimeInterface $startsAt = null,
        public ?DateTimeInterface $endsAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'status' => $this->status,
            'external_id' => $this->externalId,
            'join_url' => $this->joinUrl,
            'host_url' => $this->hostUrl,
            'password' => $this->password,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
