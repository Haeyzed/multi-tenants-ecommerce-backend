<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Settings;

use App\Http\Requests\BaseRequest;
use App\Services\Landlord\Settings\PlatformSettingsService;
use Illuminate\Validation\ValidationException;

/**
 * Validates PUT/PATCH payloads for a landlord platform settings domain.
 */
class UpdatePlatformSettingsDomainRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $domain = (string) $this->route('domain');

        return match ($domain) {
            'platform' => [
                'platform\.name' => ['sometimes', 'string', 'max:255'],
                'platform\.support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'platform\.support_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
                'platform\.maintenance_mode' => ['sometimes', 'boolean'],
                'platform\.maintenance_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ],
            'registration' => [
                'registration\.tenant_registration_enabled' => ['sometimes', 'boolean'],
                'registration\.default_plan_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            ],
            'localization' => [
                'localization\.default_currency' => ['sometimes', 'string', 'size:3'],
                'localization\.default_timezone' => ['sometimes', 'string', 'timezone:all', 'max:100'],
            ],
            default => throw ValidationException::withMessages([
                'domain' => ['Unknown settings domain ['.$domain.'].'],
            ]),
        };
    }

    /**
     * Validated settings keyed by platform setting name.
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $domain = (string) $this->route('domain');

        if (! array_key_exists($domain, PlatformSettingsService::DOMAINS)) {
            return [];
        }

        return $this->validated();
    }
}
