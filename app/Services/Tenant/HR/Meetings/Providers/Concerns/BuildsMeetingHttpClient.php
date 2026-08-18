<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Meetings\Providers\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait BuildsMeetingHttpClient
{
    protected function meetingHttpClient(?string $token = null, ?string $baseUrl = null): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('interview_meetings.timeout', 15))
            ->connectTimeout((int) config('interview_meetings.connect_timeout', 5))
            ->retry([100, 500, 1000]);

        if ($baseUrl !== null && $baseUrl !== '') {
            $request = $request->baseUrl($baseUrl);
        }

        if ($token !== null && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $credentials
     */
    protected function missingCredentialKeys(array $keys, array $credentials): array
    {
        $missing = [];

        foreach ($keys as $key) {
            $value = $credentials[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
