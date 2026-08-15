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
     * Whether a loyalty program should default to active when first created.
     */
    public function loyaltyIsActive(): bool
    {
        return filter_var($this->get('loyalty.is_active', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Points awarded per currency unit spent (e.g. "1.00" for 1 point per unit).
     */
    public function loyaltyPointsPerCurrencyUnit(): string
    {
        return $this->get('loyalty.points_per_currency_unit', '1.00') ?? '1.00';
    }

    /**
     * Points required to discount one currency unit.
     */
    public function loyaltyRedemptionPointsPerCurrency(): int
    {
        return max(1, (int) ($this->get('loyalty.redemption_points_per_currency', '100') ?? '100'));
    }

    /**
     * Smallest redeemable point amount.
     */
    public function loyaltyMinRedemptionPoints(): int
    {
        return max(1, (int) ($this->get('loyalty.min_redemption_points', '100') ?? '100'));
    }

    /**
     * Largest share of an order subtotal that points may cover, as a percent.
     */
    public function loyaltyMaxRedemptionPercent(): string
    {
        return $this->get('loyalty.max_redemption_percent', '100.00') ?? '100.00';
    }

    /**
     * Whether points are awarded automatically when an order is paid.
     */
    public function loyaltyEarnOnOrderPaid(): bool
    {
        return filter_var($this->get('loyalty.earn_on_order_paid', 'true'), FILTER_VALIDATE_BOOLEAN);
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
