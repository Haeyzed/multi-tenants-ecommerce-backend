<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Meetings;

use App\Contracts\Interview\InterviewMeetingProvider;
use App\Enums\Tenant\HR\MeetingProvider;
use App\Models\Tenant\InterviewMeetingProviderSetting;
use App\Services\Tenant\HR\RecruitmentActivityService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-scoped meeting provider enablement and encrypted credentials.
 */
class InterviewMeetingProviderSettingService
{
    /**
     * @var list<string>
     */
    private const SECRET_KEYS = [
        'client_secret',
        'secret',
        'access_token',
        'refresh_token',
        'password',
        'api_key',
        'api_secret',
    ];

    public function __construct(
        private readonly InterviewMeetingManager $manager,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $rows = [];

        foreach ($this->manager->drivers() as $name) {
            $rows[] = $this->toPublicArray($name);
        }

        return $rows;
    }

    /**
     * @param  array{enabled?: bool|null, credentials?: array<string, mixed>|null}  $data
     * @return array<string, mixed>
     */
    public function upsert(string $provider, array $data): array
    {
        $provider = $this->assertKnown($provider);

        if (! Schema::hasTable('interview_meeting_provider_settings')) {
            throw ValidationException::withMessages([
                'provider' => ['Meeting provider settings are not available.'],
            ]);
        }

        /** @var InterviewMeetingProviderSetting $record */
        $record = InterviewMeetingProviderSetting::query()->firstOrNew(['provider' => $provider]);

        if (array_key_exists('enabled', $data) && $data['enabled'] !== null) {
            $record->enabled = (bool) $data['enabled'];
        }

        if (array_key_exists('credentials', $data) && is_array($data['credentials'])) {
            $existing = is_array($record->credentials) ? $record->credentials : [];
            $record->credentials = array_merge($existing, $this->sanitizeIncomingCredentials($data['credentials']));
        }

        $record->save();

        $this->activities->record($record, 'provider_configured', null, [
            'provider' => $provider,
            'enabled' => $record->enabled,
        ]);

        return $this->toPublicArray($provider, $record);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialsFor(string $provider): array
    {
        if (! Schema::hasTable('interview_meeting_provider_settings')) {
            return [];
        }

        $record = InterviewMeetingProviderSetting::query()
            ->where('provider', $provider)
            ->first();

        return is_array($record?->credentials) ? $record->credentials : [];
    }

    public function isEnabled(string $provider): bool
    {
        if ($provider === MeetingProvider::Manual->value || $provider === MeetingProvider::Fake->value) {
            return true;
        }

        if (! Schema::hasTable('interview_meeting_provider_settings')) {
            return false;
        }

        return (bool) InterviewMeetingProviderSetting::query()
            ->where('provider', $provider)
            ->value('enabled');
    }

    /**
     * @return array<string, mixed>
     */
    public function test(string $provider): array
    {
        $provider = $this->assertKnown($provider);
        $driver = $this->manager->driver($provider);
        $credentials = $this->credentialsFor($provider);

        $driver->testConnection($credentials);

        return [
            'provider' => $provider,
            'ok' => true,
            'configured' => $this->isFullyConfigured($driver, $credentials),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(string $provider, ?InterviewMeetingProviderSetting $record = null): array
    {
        $driver = $this->manager->driver($provider);
        $record ??= Schema::hasTable('interview_meeting_provider_settings')
            ? InterviewMeetingProviderSetting::query()->where('provider', $provider)->first()
            : null;
        $credentials = is_array($record?->credentials) ? $record->credentials : [];
        $configuredKeys = [];

        foreach (array_keys($credentials) as $key) {
            if (! is_string($key) || $this->isSecretKey($key)) {
                continue;
            }

            $value = $credentials[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $configuredKeys[] = $key;
            }
        }

        $enabled = $provider === MeetingProvider::Manual->value
            || $provider === MeetingProvider::Fake->value
            || (bool) ($record?->enabled ?? false);

        return [
            'provider' => $provider,
            'enabled' => $enabled,
            'configured' => $this->isFullyConfigured($driver, $credentials),
            'always_available' => $provider === MeetingProvider::Manual->value,
            'capabilities' => $driver->capabilities()->toArray(),
            'configured_fields' => $configuredKeys,
        ];
    }

    protected function isFullyConfigured(InterviewMeetingProvider $driver, array $credentials): bool
    {
        if (! $driver->capabilities()->requiresExternalApi) {
            return true;
        }

        return $driver->isConfigured($credentials);
    }

    protected function assertKnown(string $provider): string
    {
        $provider = Str::lower($provider);

        if (! in_array($provider, $this->manager->drivers(), true)) {
            throw ValidationException::withMessages([
                'provider' => ["Unsupported meeting provider [{$provider}]."],
            ]);
        }

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    protected function sanitizeIncomingCredentials(array $credentials): array
    {
        $clean = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_string($value) && Str::contains($value, '*')) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    protected function isSecretKey(string $key): bool
    {
        $normalized = Str::lower($key);

        foreach (self::SECRET_KEYS as $secretKey) {
            if ($normalized === $secretKey || Str::contains($normalized, $secretKey)) {
                return true;
            }
        }

        return false;
    }
}
