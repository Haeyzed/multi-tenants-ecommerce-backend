<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\CommerceSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * Key/value commerce settings for the current tenant.
 */
class CommerceSettingService
{
    /**
     * Domain → key → schema (type + default).
     *
     * @var array<string, array<string, array{type: string, default: mixed}>>
     */
    public const array DOMAINS = [
        'checkout' => [
            'checkout.guest_checkout' => ['type' => 'bool', 'default' => false],
            'checkout.minimum_order_amount' => ['type' => 'money', 'default' => '0'],
            'checkout.require_phone' => ['type' => 'bool', 'default' => false],
            'checkout.allow_order_notes' => ['type' => 'bool', 'default' => true],
        ],
        'order' => [
            'returns.window_days' => ['type' => 'int', 'default' => 14],
            'order.cancellation_window_hours' => ['type' => 'int', 'default' => 24],
        ],
        'inventory' => [
            'inventory.allow_negative_stock' => ['type' => 'bool', 'default' => false],
            'inventory.default_low_stock_threshold' => ['type' => 'int', 'default' => 5],
            'inventory.reserve_on_checkout' => ['type' => 'bool', 'default' => true],
        ],
        'payment' => [
            'payment.default_gateway' => ['type' => 'nullable_string', 'default' => null],
            'payment.timeout_minutes' => ['type' => 'int', 'default' => 30],
        ],
        'pos' => [
            'pos.default_warehouse_id' => ['type' => 'nullable_int', 'default' => null],
            'pos.receipt_prefix' => ['type' => 'string', 'default' => 'POS'],
            'pos.require_customer' => ['type' => 'bool', 'default' => false],
        ],
        'delivery' => [
            'delivery.assignment_strategy' => ['type' => 'string', 'default' => 'manual'],
            'delivery.assignment_radius_km' => ['type' => 'float', 'default' => 15.0],
            'delivery.require_proof_of_delivery' => ['type' => 'bool', 'default' => false],
        ],
        'store_status' => [
            'store.status' => ['type' => 'string', 'default' => 'open'],
            'store.maintenance_message' => ['type' => 'nullable_string', 'default' => null],
        ],
        'ecommerce' => [
            'is_marketplace_enabled' => ['type' => 'bool', 'default' => false],
            'marketplace.commission_type' => ['type' => 'string', 'default' => 'percentage'],
            'marketplace.commission_rate' => ['type' => 'money', 'default' => '10'],
            'marketplace.commission_fixed_amount' => ['type' => 'money', 'default' => '0'],
            'marketplace.refund_window_days' => ['type' => 'int', 'default' => 0],
            'seller.allow_registration' => ['type' => 'bool', 'default' => false],
        ],
        'customer' => [
            'customer.registration_enabled' => ['type' => 'bool', 'default' => true],
            'customer.approval_required' => ['type' => 'bool', 'default' => false],
            'customer.default_group_id' => ['type' => 'nullable_int', 'default' => null],
        ],
        'notification' => [
            'notification.email_enabled' => ['type' => 'bool', 'default' => true],
            'notification.sms_enabled' => ['type' => 'bool', 'default' => false],
            'notification.push_enabled' => ['type' => 'bool', 'default' => true],
        ],
        'pricing' => [
            'pricing.tax_inclusive' => ['type' => 'bool', 'default' => false],
            'pricing.display_includes_tax' => ['type' => 'bool', 'default' => false],
        ],
        'tax' => [
            'tax.enabled' => ['type' => 'bool', 'default' => true],
        ],
        'shipping' => [
            'shipping.enabled' => ['type' => 'bool', 'default' => true],
            'shipping.free_shipping_minimum' => ['type' => 'money', 'default' => '0'],
        ],
        'cms' => [
            'cms.blog_enabled' => ['type' => 'bool', 'default' => true],
            'cms.pages_enabled' => ['type' => 'bool', 'default' => true],
        ],
        'hr' => [
            'hr.enabled' => ['type' => 'bool', 'default' => true],
            'hr.employee_code_prefix' => ['type' => 'string', 'default' => 'EMP'],
            'hr.default_employment_status' => ['type' => 'string', 'default' => 'active'],
            'hr.attendance.enabled' => ['type' => 'bool', 'default' => true],
            'hr.working_days' => ['type' => 'string', 'default' => '1,2,3,4,5'],
            'hr.work_start_time' => ['type' => 'string', 'default' => '09:00'],
            'hr.late_tolerance_minutes' => ['type' => 'int', 'default' => 15],
            'hr.leave.enabled' => ['type' => 'bool', 'default' => true],
            'hr.leave.approval_required' => ['type' => 'bool', 'default' => true],
            'hr.leave.max_consecutive_days' => ['type' => 'int', 'default' => 0],
            'hr.leave.year_start_month' => ['type' => 'int', 'default' => 1],
            'hr.payroll.enabled' => ['type' => 'bool', 'default' => true],
            'hr.payroll.frequency' => ['type' => 'string', 'default' => 'monthly'],
            'hr.payroll.currency' => ['type' => 'string', 'default' => 'NGN'],
            'hr.payroll.approval_required' => ['type' => 'bool', 'default' => false],
            'hr.payroll.payment_day' => ['type' => 'int', 'default' => 25],
            'hr.payroll.expense_account_id' => ['type' => 'nullable_int', 'default' => null],
            'hr.payroll.payable_account_id' => ['type' => 'nullable_int', 'default' => null],
            'hr.overtime.enabled' => ['type' => 'bool', 'default' => false],
            'hr.overtime.rate_percent' => ['type' => 'int', 'default' => 150],
            'hr.working_hours_per_day' => ['type' => 'int', 'default' => 8],
            'hr.leave.carry_over_enabled' => ['type' => 'bool', 'default' => false],
            'hr.leave.carry_over_max_days' => ['type' => 'int', 'default' => 5],
            'hr.notifications.leave' => ['type' => 'bool', 'default' => true],
            'hr.notifications.payroll' => ['type' => 'bool', 'default' => true],
        ],
    ];

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
     * Known setting keys for a domain, cast with defaults.
     *
     * @return array<string, mixed>
     */
    public function getDomain(string $domain): array
    {
        $schema = $this->domainSchema($domain);
        $settings = [];

        foreach ($schema as $key => $definition) {
            $raw = $this->get($key);
            $settings[$key] = $this->castValue(
                $raw,
                $definition['type'],
                $definition['default'],
            );
        }

        if ($domain === 'delivery') {
            $this->syncDeliveryConfig($settings);
        }

        return $settings;
    }

    /**
     * Validate against the domain allowlist and persist values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateDomain(string $domain, array $values): array
    {
        $schema = $this->domainSchema($domain);
        $unknown = array_diff(array_keys($values), array_keys($schema));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unknown setting keys for domain ['.$domain.']: '.implode(', ', $unknown).'.'],
            ]);
        }

        foreach ($values as $key => $value) {
            $this->set($key, $this->serializeValue($value, $schema[$key]['type']));
        }

        return $this->getDomain($domain);
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return array_keys(self::DOMAINS);
    }

    /**
     * Whether the given domain is a commerce settings domain.
     */
    public function hasDomain(string $domain): bool
    {
        return array_key_exists($domain, self::DOMAINS);
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
     * Whether public seller self-registration is allowed.
     */
    public function allowSellerRegistration(): bool
    {
        return filter_var($this->get('seller.allow_registration', 'false'), FILTER_VALIDATE_BOOLEAN);
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
     * Hours after placement during which an order may be cancelled.
     */
    public function orderCancellationWindowHours(): int
    {
        return max(0, (int) ($this->get('order.cancellation_window_hours', '24') ?? '24'));
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
     * Resolved delivery assignment strategy (setting overrides config).
     */
    public function deliveryAssignmentStrategy(): string
    {
        $value = $this->get('delivery.assignment_strategy');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return (string) config('delivery.assignment.strategy', 'manual');
    }

    /**
     * Resolved delivery assignment radius in km (setting overrides config).
     */
    public function deliveryAssignmentRadiusKm(): float
    {
        $value = $this->get('delivery.assignment_radius_km');

        if ($value !== null && $value !== '') {
            return (float) $value;
        }

        return (float) config('delivery.assignment.radius_km', 15);
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

    /**
     * @return array<string, array{type: string, default: mixed}>
     */
    protected function domainSchema(string $domain): array
    {
        if (! $this->hasDomain($domain)) {
            throw ValidationException::withMessages([
                'domain' => ['Unknown settings domain ['.$domain.'].'],
            ]);
        }

        return self::DOMAINS[$domain];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function syncDeliveryConfig(array $settings): void
    {
        if (array_key_exists('delivery.assignment_strategy', $settings)) {
            Config::set('delivery.assignment.strategy', (string) $settings['delivery.assignment_strategy']);
        }

        if (array_key_exists('delivery.assignment_radius_km', $settings)) {
            Config::set('delivery.assignment.radius_km', (float) $settings['delivery.assignment_radius_km']);
        }
    }

    protected function castValue(?string $raw, string $type, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $raw,
            'nullable_int' => $raw === '' ? null : (int) $raw,
            'float' => (float) $raw,
            'money', 'string' => $raw,
            'nullable_string' => $raw === '' ? null : $raw,
            default => $raw,
        };
    }

    protected function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return match ($type) {
                'nullable_string', 'nullable_int' => null,
                'bool' => 'false',
                'int', 'float', 'money' => '0',
                default => null,
            };
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'int', 'nullable_int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'money' => is_numeric($value) ? (string) $value : (string) $value,
            'string', 'nullable_string' => (string) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
