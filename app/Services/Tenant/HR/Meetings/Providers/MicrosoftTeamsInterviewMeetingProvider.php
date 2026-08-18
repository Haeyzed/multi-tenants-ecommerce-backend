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
 * Microsoft Teams online meetings via Microsoft Graph.
 *
 * Application permissions require an application access policy targeting user_id.
 *
 * @see https://learn.microsoft.com/en-us/graph/api/application-post-onlinemeetings
 * @see https://learn.microsoft.com/en-us/graph/api/onlinemeeting-update
 * @see https://learn.microsoft.com/en-us/graph/api/onlinemeeting-delete
 */
class MicrosoftTeamsInterviewMeetingProvider implements InterviewMeetingProvider
{
    use BuildsMeetingHttpClient;

    /**
     * @var list<string>
     */
    private const REQUIRED_KEYS = ['tenant_id', 'client_id', 'client_secret', 'user_id'];

    public function name(): string
    {
        return MeetingProvider::MicrosoftTeams->value;
    }

    public function capabilities(): MeetingProviderCapabilities
    {
        return new MeetingProviderCapabilities(
            canCreate: true,
            canUpdate: true,
            canCancel: true,
            canGet: true,
            supportsPassword: false,
            supportsHostUrl: true,
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
        $userId = $request->requiredCredential('user_id');

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.microsoft_teams.graph_base_url'))
                ->post('/users/'.rawurlencode($userId).'/onlineMeetings', $this->payload($request))
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
        $userId = $request->requiredCredential('user_id');

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.microsoft_teams.graph_base_url'))
                ->patch(
                    '/users/'.rawurlencode($userId).'/onlineMeetings/'.rawurlencode($request->externalId),
                    $this->payload($request),
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
        $userId = $request->requiredCredential('user_id');

        try {
            $this->meetingHttpClient($token, (string) config('interview_meetings.microsoft_teams.graph_base_url'))
                ->delete('/users/'.rawurlencode($userId).'/onlineMeetings/'.rawurlencode($request->externalId))
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
        $userId = $request->requiredCredential('user_id');

        try {
            $payload = $this->meetingHttpClient($token, (string) config('interview_meetings.microsoft_teams.graph_base_url'))
                ->get('/users/'.rawurlencode($userId).'/onlineMeetings/'.rawurlencode($request->externalId))
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

        $tenantId = trim((string) $credentials['tenant_id']);
        $url = str_replace(
            '{tenant}',
            rawurlencode($tenantId),
            (string) config('interview_meetings.microsoft_teams.token_url'),
        );

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('interview_meetings.timeout', 15))
                ->connectTimeout((int) config('interview_meetings.connect_timeout', 5))
                ->post($url, [
                    'client_id' => trim((string) $credentials['client_id']),
                    'client_secret' => trim((string) $credentials['client_secret']),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
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
     * @return array<string, mixed>
     */
    protected function payload(MeetingRequest $request): array
    {
        $start = Carbon::instance(Carbon::parse($request->startsAt))->utc();
        $end = Carbon::instance(Carbon::parse($request->endsAt()))->utc();

        return [
            'subject' => $request->topic,
            'startDateTime' => $start->toRfc3339String(),
            'endDateTime' => $end->toRfc3339String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalize(array $payload): MeetingResult
    {
        $joinUrl = isset($payload['joinWebUrl']) && is_string($payload['joinWebUrl'])
            ? $payload['joinWebUrl']
            : null;

        return new MeetingResult(
            provider: $this->name(),
            status: InterviewMeetingStatus::Created->value,
            externalId: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null,
            joinUrl: $joinUrl,
            hostUrl: $joinUrl,
            startsAt: isset($payload['startDateTime']) && is_string($payload['startDateTime'])
                ? Carbon::parse($payload['startDateTime'])
                : null,
            endsAt: isset($payload['endDateTime']) && is_string($payload['endDateTime'])
                ? Carbon::parse($payload['endDateTime'])
                : null,
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
