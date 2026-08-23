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
use Throwable;

/**
 * Zoom Meetings API via Server-to-Server OAuth.
 *
 * @see https://developers.zoom.us/docs/internal-apps/s2s-oauth/
 * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/meetingCreate
 */
class ZoomInterviewMeetingProvider implements InterviewMeetingProvider
{
    use BuildsMeetingHttpClient;

    /**
     * @var list<string>
     */
    private const array REQUIRED_KEYS = ['account_id', 'client_id', 'client_secret', 'host_user_id'];

    /**
     * Name.
     *
     * @return string
     */
    public function name(): string
    {
        return MeetingProvider::Zoom->value;
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
            requiresExternalApi: true,
            requiredCredentialKeys: self::REQUIRED_KEYS,
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
        return $this->missingCredentialKeys(self::REQUIRED_KEYS, $credentials) === [];
    }

    /**
     * Test connection.
     *
     * @param  array  $credentials
     * @return void
     */
    public function testConnection(array $credentials): void
    {
        $this->accessToken($credentials);
    }

    /**
     * Create meeting.
     *
     * @param  MeetingRequest  $request
     * @return MeetingResult
     */
    public function createMeeting(MeetingRequest $request): MeetingResult
    {
        $token = $this->accessToken($request->credentials);
        $host = $request->requiredCredential('host_user_id');

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.zoom.base_url'))
                ->post('/users/'.rawurlencode($host).'/meetings', $this->payload($request))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('create', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    /**
     * Update meeting.
     *
     * @param  MeetingRequest  $request
     * @return MeetingResult
     */
    public function updateMeeting(MeetingRequest $request): MeetingResult
    {
        if ($request->externalId === null || $request->externalId === '') {
            throw new InterviewMeetingProviderException(
                'Unable to update the interview meeting. Please verify the configured meeting provider.',
                ['provider' => $this->name(), 'operation' => 'update'],
            );
        }

        $token = $this->accessToken($request->credentials);

        try {
            $this->meetingHttpClient($token, (string) config('interview_meetings.zoom.base_url'))
                ->patch('/meetings/'.rawurlencode($request->externalId), $this->payload($request))
                ->throw();

            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.zoom.base_url'))
                ->get('/meetings/'.rawurlencode($request->externalId))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('update', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    /**
     * Cancel meeting.
     *
     * @param  MeetingRequest  $request
     * @return void
     */
    public function cancelMeeting(MeetingRequest $request): void
    {
        if ($request->externalId === null || $request->externalId === '') {
            return;
        }

        $token = $this->accessToken($request->credentials);

        try {
            $this->meetingHttpClient($token, (string) config('interview_meetings.zoom.base_url'))
                ->delete('/meetings/'.rawurlencode($request->externalId))
                ->throw();
        } catch (Throwable $exception) {
            $this->fail('cancel', $request, $exception);
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
        if ($request->externalId === null || $request->externalId === '') {
            return null;
        }

        $token = $this->accessToken($request->credentials);

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.zoom.base_url'))
                ->get('/meetings/'.rawurlencode($request->externalId))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            $this->fail('get', $request, $exception);
        }

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    /**
     * Access token.
     *
     * @param  array<string, mixed>  $credentials
     * @return string
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

        $accountId = trim((string) $credentials['account_id']);
        $clientId = trim((string) $credentials['client_id']);
        $clientSecret = trim((string) $credentials['client_secret']);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('interview_meetings.timeout', 15))
                ->connectTimeout((int) config('interview_meetings.connect_timeout', 5))
                ->withBasicAuth($clientId, $clientSecret)
                ->post((string) config('interview_meetings.zoom.oauth_url'), [
                    'grant_type' => 'account_credentials',
                    'account_id' => $accountId,
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
     * Payload.
     *
     * @param  MeetingRequest  $request
     * @return array<string, mixed>
     */
    protected function payload(MeetingRequest $request): array
    {
        $startsAt = Carbon::instance(Carbon::parse($request->startsAt))->utc();

        $payload = [
            'topic' => $request->topic,
            'type' => 2,
            'start_time' => $startsAt->format('Y-m-d\TH:i:s\Z'),
            'duration' => $request->durationMinutes,
            'timezone' => 'UTC',
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => true,
            ],
        ];

        if ($request->agenda !== null && $request->agenda !== '') {
            $payload['agenda'] = $request->agenda;
        }

        if ($request->password !== null && $request->password !== '') {
            $payload['password'] = $request->password;
        }

        return $payload;
    }

    /**
     * Normalize.
     *
     * @param  array<string, mixed>  $payload
     * @return MeetingResult
     */
    protected function normalize(array $payload): MeetingResult
    {
        $start = isset($payload['start_time']) && is_string($payload['start_time'])
            ? Carbon::parse($payload['start_time'])
            : null;
        $duration = isset($payload['duration']) ? (int) $payload['duration'] : null;

        return new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: isset($payload['id']) ? (string) $payload['id'] : null,
            joinUrl: isset($payload['join_url']) && is_string($payload['join_url']) ? $payload['join_url'] : null,
            hostUrl: isset($payload['start_url']) && is_string($payload['start_url']) ? $payload['start_url'] : null,
            password: isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : null,
            startsAt: $start,
            endsAt: $start !== null && $duration !== null ? $start->copy()->addMinutes($duration) : null,
        );
    }

    /**
     * Fail.
     *
     * @param  string  $operation
     * @param  ?MeetingRequest  $request
     * @param  Throwable  $exception
     * @return never
     */
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

    /**
     * Operation label.
     *
     * @param  string  $operation
     * @return string
     */
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
