<?php

declare(strict_types=1);

namespace App\Services\Tenant\Settings;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-facing settings: commerce KV domains plus a read-only store profile snapshot.
 */
class TenantSettingsService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * Read-only store/localization snapshot from TenantProfile.
     *
     * @return array<string, mixed>
     */
    public function storeSnapshot(): array
    {
        $profile = $this->resolveProfile();

        if ($profile === null) {
            return [
                'display_name' => null,
                'slug' => null,
                'description' => null,
                'email' => null,
                'phone' => null,
                'website' => null,
                'address' => null,
                'timezone' => null,
                'currency' => null,
                'language' => null,
                'logo_url' => null,
                'cover_url' => null,
            ];
        }

        $profile->loadMissing(['currency', 'language']);

        return [
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'description' => $profile->description,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'website' => $profile->website,
            'address' => $profile->address,
            'timezone' => $profile->timezone,
            'currency' => $profile->currency === null ? null : [
                'id' => $profile->currency->id,
                'code' => $profile->currency->code,
                'symbol' => $profile->currency->symbol ?? null,
            ],
            'language' => $profile->language === null ? null : [
                'id' => $profile->language->id,
                'code' => $profile->language->code ?? null,
                'name' => $profile->language->name ?? null,
            ],
            'logo_url' => $profile->logo_url,
            'cover_url' => $profile->cover_url,
        ];
    }

    /**
     * Get domain.
     *
     * @param  string  $domain
     * @return array<string, mixed>
     */
    public function getDomain(string $domain): array
    {
        if ($domain === 'store') {
            return $this->storeSnapshot();
        }

        if ($domain === 'hr') {
            throw ValidationException::withMessages([
                'domain' => ['HR settings are managed through the HR settings API.'],
            ]);
        }

        return $this->commerceSettings->getDomain($domain);
    }

    /**
     * Update domain.
     *
     * @param  string  $domain
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateDomain(string $domain, array $values): array
    {
        if ($domain === 'store') {
            throw ValidationException::withMessages([
                'domain' => ['Store profile settings are read-only from the tenant API.'],
            ]);
        }

        if ($domain === 'hr') {
            throw ValidationException::withMessages([
                'domain' => ['HR settings are managed through the HR settings API.'],
            ]);
        }

        return $this->commerceSettings->updateDomain($domain, $values);
    }

    /**
     * Domains.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return ['store', ...array_values(array_filter(
            $this->commerceSettings->domains(),
            static fn (string $domain): bool => $domain !== 'hr',
        ))];
    }

    /**
     * Resolve profile.
     *
     * @return ?TenantProfile
     */
    protected function resolveProfile(): ?TenantProfile
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $tenant->loadMissing('profile');

        return $tenant->profile;
    }
}
