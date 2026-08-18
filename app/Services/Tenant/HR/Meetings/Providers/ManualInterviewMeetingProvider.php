<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Meetings\Providers;

use App\Contracts\Interview\InterviewMeetingProvider;
use App\DTO\Interview\MeetingProviderCapabilities;
use App\DTO\Interview\MeetingRequest;
use App\DTO\Interview\MeetingResult;
use App\Enums\Tenant\HR\InterviewMeetingStatus;
use App\Enums\Tenant\HR\MeetingProvider;
use App\Exceptions\Interview\InterviewMeetingProviderException;

/**
 * Stores a supplied join URL. Never calls an external API.
 */
class ManualInterviewMeetingProvider implements InterviewMeetingProvider
{
    public function name(): string
    {
        return MeetingProvider::Manual->value;
    }

    public function capabilities(): MeetingProviderCapabilities
    {
        return new MeetingProviderCapabilities(
            canCreate: true,
            canUpdate: true,
            canCancel: true,
            canGet: true,
            supportsPassword: true,
            supportsHostUrl: false,
            requiresExternalApi: false,
        );
    }

    public function isConfigured(array $credentials): bool
    {
        return true;
    }

    public function testConnection(array $credentials): void {}

    public function createMeeting(MeetingRequest $request): MeetingResult
    {
        $joinUrl = $request->joinUrl;

        if ($joinUrl === null || $joinUrl === '') {
            throw new InterviewMeetingProviderException(
                'A meeting URL is required when using the manual meeting provider.',
                ['provider' => $this->name(), 'operation' => 'create'],
            );
        }

        return $this->result($request, $joinUrl);
    }

    public function updateMeeting(MeetingRequest $request): MeetingResult
    {
        return $this->createMeeting($request);
    }

    public function cancelMeeting(MeetingRequest $request): void {}

    public function getMeeting(MeetingRequest $request): ?MeetingResult
    {
        if ($request->joinUrl === null || $request->joinUrl === '') {
            return null;
        }

        return $this->result($request, $request->joinUrl);
    }

    protected function result(MeetingRequest $request, string $joinUrl): MeetingResult
    {
        return new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: $request->externalId,
            joinUrl: $joinUrl,
            password: $request->password,
            startsAt: $request->startsAt,
            endsAt: $request->endsAt(),
        );
    }
}
