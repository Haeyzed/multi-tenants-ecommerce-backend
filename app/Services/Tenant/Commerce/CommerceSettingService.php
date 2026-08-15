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
     * Whether marketplace features are enabled for this tenant.
     */
    public function isMarketplaceEnabled(): bool
    {
        return filter_var($this->get('is_marketplace_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Enable or disable marketplace mode.
     */
    public function setMarketplaceEnabled(bool $enabled): void
    {
        $this->set('is_marketplace_enabled', $enabled ? 'true' : 'false');
    }

    /**
     * Default commission type for sellers without an override.
     */
    public function defaultCommissionType(): string
    {
        return $this->get('marketplace.commission_type', 'percentage') ?? 'percentage';
    }

    /**
     * Default commission rate percent (e.g. "10" for 10%).
     */
    public function defaultCommissionRate(): string
    {
        return $this->get('marketplace.commission_rate', '10') ?? '10';
    }

    /**
     * Default fixed commission amount.
     */
    public function defaultCommissionFixedAmount(): string
    {
        return $this->get('marketplace.commission_fixed_amount', '0') ?? '0';
    }

    /**
     * Days after delivery before a seller payout is eligible.
     */
    public function marketplaceRefundWindowDays(): int
    {
        return max(0, (int) ($this->get('marketplace.refund_window_days', '0') ?? '0'));
    }

    /**
     * Hours of inactivity before an active cart is considered abandoned.
     */
    public function cartAbandonAfterHours(): int
    {
        return max(1, (int) ($this->get('cart.abandon_after_hours', '24') ?? '24'));
    }

    /**
     * Days after delivery (or fulfillment) during which returns are allowed.
     */
    public function returnWindowDays(): int
    {
        return max(0, (int) ($this->get('returns.window_days', '14') ?? '14'));
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
