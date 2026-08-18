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
use App\Services\Tenant\HR\Meetings\Providers\Concerns\BuildsMeetingHttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Google Meet via Calendar events.insert with conferenceData.createRequest.
 *
 * Requires a tenant-stored OAuth refresh token. This driver does not collect Google passwords.
 *
 * @see https://developers.google.com/workspace/calendar/api/v3/reference/events/insert
 * @see https://developers.google.com/workspace/calendar/api/guides/create-events#conference-data
 */
class GoogleMeetInterviewMeetingProvider implements InterviewMeetingProvider
{
    use BuildsMeetingHttpClient;

    /**
     * @var list<string>
     */
    private const REQUIRED_KEYS = ['client_id', 'client_secret', 'refresh_token'];

    public function name(): string
    {
        return MeetingProvider::GoogleMeet->value;
    }

    public function capabilities(): MeetingProviderCapabilities
    {
        return new MeetingProviderCapabilities(
            canCreate: true,
            canUpdate: true,
            canCancel: true,
            canGet: true,
            supportsPassword: false,
            supportsHostUrl: false,
            requiresExternalApi: true,
            requiredCredentialKeys: self::REQUIRED_KEYS,
        );
    }

    public function isConfigured(array $credentials): bool
    {
        return $this->missingCredentialKeys(self::REQUIRED_KEYS, $credentials) === [];
    }

    public function testConnection(array $credentials): void
    {
        $this->accessToken($credentials);
    }

    public function createMeeting(MeetingRequest $request): MeetingResult
    {
        $token = $this->accessToken($request->credentials);
        $calendarId = $this->calendarId($request->credentials);

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.google_meet.calendar_base_url'))
                ->post('/calendars/'.rawurlencode($calendarId).'/events?conferenceDataVersion=1', $this->eventBody($request, createConference: true))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('create', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    public function updateMeeting(MeetingRequest $request): MeetingResult
    {
        if ($request->externalId === null || $request->externalId === '') {
            throw new InterviewMeetingProviderException(
                'Unable to update the interview meeting. Please verify the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'update'],
            );
        }

        $token = $this->accessToken($request->credentials);
        $calendarId = $this->calendarId($request->credentials);

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.google_meet.calendar_base_url'))
                ->patch(
                    '/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($request->externalId).'?conferenceDataVersion=1',
                    $this->eventBody($request, createConference: false),
                )
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('update', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    public function cancelMeeting(MeetingRequest $request): void
    {
        if ($request->externalId === null || $request->externalId === '') {
            return;
        }

        $token = $this->accessToken($request->credentials);
        $calendarId = $this->calendarId($request->credentials);

        try {
            $this->meetingHttpClient($token, (string) config('interview_meetings.google_meet.calendar_base_url'))
                ->delete('/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($request->externalId))
                ->throw();
        } catch (Throwable $exception) {
            $this->fail('cancel', $request, $exception);
        }
    }

    public function getMeeting(MeetingRequest $request): ?MeetingResult
    {
        if ($request->externalId === null || $request->externalId === '') {
            return null;
        }

        $token = $this->accessToken($request->credentials);
        $calendarId = $this->calendarId($request->credentials);

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.google_meet.calendar_base_url'))
                ->get('/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($request->externalId))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('get', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function accessToken(array $credentials): string
    {
        $missing = $this->missingCredentialKeys(self::REQUIRED_KEYS, $credentials);

        if ($missing !== []) {
            throw new InterviewMeetingProviderException(
                'Unable to authenticate with the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'auth', 'missing' => $missing],
            );
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('interview_meetings.timeout', 15))
                ->connectTimeout((int) config('interview_meetings.connect_timeout', 5))
                ->post((string) config('interview_meetings.google_meet.token_url'), [
                    'grant_type' => 'refresh_token',
                    'client_id' => trim((string) $credentials['client_id']),
                    'client_secret' => trim((string) $credentials['client_secret']),
                    'refresh_token' => trim((string) $credentials['refresh_token']),
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('auth', null, $exception);
        }

        $token = is_array($response) ? ($response['access_token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw new InterviewMeetingProviderException(
                'Unable to authenticate with the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'auth'],
            );
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function calendarId(array $credentials): string
    {
        $calendarId = $credentials['calendar_id'] ?? 'primary';

        return is_string($calendarId) && trim($calendarId) !== '' ? trim($calendarId) : 'primary';
    }

    /**
     * @return array<string, mixed>
     */
    protected function eventBody(MeetingRequest $request, bool $createConference): array
    {
        $start = Carbon::instance(Carbon::parse($request->startsAt))->utc();
        $end = Carbon::instance(Carbon::parse($request->endsAt()))->utc();

        $body = [
            'summary' => $request->topic,
            'description' => $request->agenda,
            'start' => [
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => $request->timezone !== '' ? $request->timezone : 'UTC',
            ],
            'end' => [
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => $request->timezone !== '' ? $request->timezone : 'UTC',
            ],
        ];

        if ($createConference) {
            $body['conferenceData'] = [
                'createRequest' => [
                    'requestId' => Str::uuid()->toString(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalize(array $payload): MeetingResult
    {
        $entryPoints = $payload['conferenceData']['entryPoints'] ?? [];
        $joinUrl = null;

        if (is_array($entryPoints)) {
            foreach ($entryPoints as $entry) {
                if (is_array($entry) && ($entry['entryPointType'] ?? null) === 'video' && is_string($entry['uri'] ?? null)) {
                    $joinUrl = $entry['uri'];
                    break;
                }
            }
        }

        if ($joinUrl === null && isset($payload['hangoutLink']) && is_string($payload['hangoutLink'])) {
            $joinUrl = $payload['hangoutLink'];
        }

        $start = isset($payload['start']['dateTime']) && is_string($payload['start']['dateTime'])
            ? Carbon::parse($payload['start']['dateTime'])
            : null;
        $end = isset($payload['end']['dateTime']) && is_string($payload['end']['dateTime'])
            ? Carbon::parse($payload['end']['dateTime'])
            : null;

        return new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null,
            joinUrl: $joinUrl,
            startsAt: $start,
            endsAt: $end,
        );
    }

    protected function fail(string $operation, ?MeetingRequest $request, Throwable $exception): never
    {
        $status = $exception instanceof RequestException ? $exception->response?->status() : null;

        Log::warning('Interview meeting provider failed', [
            'provider' => $this->name(),
            'operation' => $operation,
            'interview_id' => $request?->interviewId,
            'external_id' => $request?->externalId,
            'http_status' => $status,
            'tenant_id' => tenant()?->getTenantKey(),
        ]);

        throw new InterviewMeetingProviderException(
            'Unable to '.$this->operationLabel($operation).' the interview meeting. Please verify the configured meeting provider.',
            [
                'provider' => $this->name(),
                'operation' => $operation,
                'interview_id' => $request?->interviewId,
                'http_status' => $status,
            ],
            $exception,
        );
    }

    protected function operationLabel(string $operation): string
    {
        return match ($operation) {
            'create' => 'create',
            'update' => 'update',
            'cancel' => 'cancel',
            'get' => 'load',
            default => 'complete',
        };
    }
}
