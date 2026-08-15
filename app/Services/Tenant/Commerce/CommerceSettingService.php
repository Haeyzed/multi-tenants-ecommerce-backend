<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\CommerceSetting;

/**
 * Key/value commerce settings for the current tenant.
 */
class CommerceSettingService
{
    /**
     * Read a commerce setting value.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = CommerceSetting::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Persist a commerce setting value.
     */
    public function set(string $key, ?string $value): void
    {
        CommerceSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Tax rate percent string (default 0).
     */
    public function taxRate(): string
    {
        return $this->get('tax_rate', '0') ?? '0';
    }

    /**
     * Storefront currency code from tenant profile when tenancy is initialized.
     */
    public function currencyCode(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            /** @var Tenant|null $tenant */
            $tenant = tenant();

            if ($tenant !== null) {
                $tenant->loadMissing('profile.currency');
                $code = $tenant->profile?->currency?->code;

                if (is_string($code) && $code !== '') {
                    return strtoupper($code);
                }
            }
        }

        return 'NGN';
    }
}
