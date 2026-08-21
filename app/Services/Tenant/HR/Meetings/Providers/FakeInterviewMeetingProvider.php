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
use Illuminate\Support\Str;

/**
 * In-memory meeting driver for automated tests. Never makes HTTP calls.
 */
class FakeInterviewMeetingProvider implements InterviewMeetingProvider
{
    /**
     * @var array<string, MeetingResult>
     */
    protected static array $meetings = [];

    public static bool $failNext = false;

    public static bool $rejectUpdate = false;

    public static bool $rejectCancel = false;

    /**
     * Reset.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$meetings = [];
        self::$failNext = false;
        self::$rejectUpdate = false;
        self::$rejectCancel = false;
    }

    /**
     * Meetings.
     *
     * @return array<string, MeetingResult>
     */
    public static function meetings(): array
    {
        return self::$meetings;
    }

    /**
     * Name.
     *
     * @return string
     */
    public function name(): string
    {
        return MeetingProvider::Fake->value;
    }

    /**
     * Capabilities.
     *
     * @return MeetingProviderCapabilities
     */
    public function capabilities(): MeetingProviderCapabilities
    {
        return new MeetingProviderCapabilities(
            canCreate: true,
            canUpdate: true,
            canCancel: true,
            canGet: true,
            supportsPassword: true,
            supportsHostUrl: true,
            requiresExternalApi: false,
        );
    }

    /**
     * Is configured.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function isConfigured(array $credentials): bool
    {
        return true;
    }

    /**
     * Test connection.
     *
     * @param  array  $credentials
     * @return void
     */
    public function testConnection(array $credentials): void
    {
        if (self::$failNext) {
            self::$failNext = false;

            throw new InterviewMeetingProviderException(
                'Unable to authenticate with the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'test'],
            );
        }
    }

    /**
     * Create meeting.
     *
     * @param  MeetingRequest  $request
     * @return MeetingResult
     */
    public function createMeeting(MeetingRequest $request): MeetingResult
    {
        $this->throwIfFailing('create');

        $id = 'fake-'.Str::uuid()->toString();
        $result = new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: $id,
            joinUrl: $request->joinUrl ?: 'https://meet.example.test/'.$id,
            hostUrl: 'https://meet.example.test/host/'.$id,
            password: $request->password,
            startsAt: $request->startsAt,
            endsAt: $request->endsAt(),
        );

        self::$meetings[$id] = $result;

        return $result;
    }

    /**
     * Update meeting.
     *
     * @param  MeetingRequest  $request
     * @return MeetingResult
     */
    public function updateMeeting(MeetingRequest $request): MeetingResult
    {
        if (self::$rejectUpdate) {
            throw new InterviewMeetingProviderException(
                'Unable to update the interview meeting. Please verify the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'update'],
            );
        }

        $this->throwIfFailing('update');

        $id = $request->externalId ?? 'fake-'.Str::uuid()->toString();
        $existing = self::$meetings[$id] ?? null;

        $result = new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: $id,
            joinUrl: $request->joinUrl ?: ($existing?->joinUrl ?? 'https://meet.example.test/'.$id),
            hostUrl: $existing?->hostUrl ?? 'https://meet.example.test/host/'.$id,
            password: $request->password ?? $existing?->password,
            startsAt: $request->startsAt,
            endsAt: $request->endsAt(),
        );

        self::$meetings[$id] = $result;

        return $result;
    }

    /**
     * Cancel meeting.
     *
     * @param  MeetingRequest  $request
     * @return void
     */
    public function cancelMeeting(MeetingRequest $request): void
    {
        if (self::$rejectCancel) {
            throw new InterviewMeetingProviderException(
                'Unable to cancel the interview meeting. Please verify the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'cancel'],
            );
        }

        $this->throwIfFailing('cancel');

        if ($request->externalId !== null) {
            unset(self::$meetings[$request->externalId]);
        }
    }

    /**
     * Get meeting.
     *
     * @param  MeetingRequest  $request
     * @return ?MeetingResult
     */
    public function getMeeting(MeetingRequest $request): ?MeetingResult
    {
        if ($request->externalId === null) {
            return null;
        }

        return self::$meetings[$request->externalId] ?? null;
    }

    /**
     * Throw if failing.
     *
     * @param  string  $operation
     * @return void
     */
    protected function throwIfFailing(string $operation): void
    {
        if (! self::$failNext) {
            return;
        }

        self::$failNext = false;

        throw new InterviewMeetingProviderException(
            'Unable to create the interview meeting. Please verify the configured meeting provider.',
            ['provider' => $this->name(), 'operation' => $operation],
        );
    }
}
