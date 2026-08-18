<?php

declare(strict_types=1);

namespace App\Contracts\Interview;

use App\DTO\Interview\MeetingProviderCapabilities;
use App\DTO\Interview\MeetingRequest;
use App\DTO\Interview\MeetingResult;
use App\Exceptions\Interview\InterviewMeetingProviderException;

/**
 * Driver contract for creating, updating, and cancelling interview meetings.
 */
interface InterviewMeetingProvider
{
    /**
     * Stable driver name (e.g. zoom, google_meet, microsoft_teams, manual).
     */
    public function name(): string;

    public function capabilities(): MeetingProviderCapabilities;

    /**
     * Whether tenant credentials are complete enough to call the provider.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function isConfigured(array $credentials): bool;

    /**
     * Authenticate without creating a meeting.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws InterviewMeetingProviderException
     */
    public function testConnection(array $credentials): void;

    /**
     * @throws InterviewMeetingProviderException
     */
    public function createMeeting(MeetingRequest $request): MeetingResult;

    /**
     * @throws InterviewMeetingProviderException
     */
    public function updateMeeting(MeetingRequest $request): MeetingResult;

    /**
     * @throws InterviewMeetingProviderException
     */
    public function cancelMeeting(MeetingRequest $request): void;

    /**
     * @throws InterviewMeetingProviderException
     */
    public function getMeeting(MeetingRequest $request): ?MeetingResult;
}
