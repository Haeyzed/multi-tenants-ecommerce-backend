<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Settings;

use App\Enums\Tenant\Marketplace\CommissionType;
use App\Http\Requests\BaseRequest;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates PUT/PATCH payloads for a settings domain.
 */
class UpdateSettingsDomainRequest extends BaseRequest
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

        // Dotted setting keys are literal request keys; escape so Laravel does not treat them as nested paths.
        return match ($domain) {
            'store' => [],
            'checkout' => [
                'checkout\.guest_checkout' => ['sometimes', 'boolean'],
                'checkout\.minimum_order_amount' => ['sometimes', 'numeric', 'min:0'],
                'checkout\.require_phone' => ['sometimes', 'boolean'],
                'checkout\.allow_order_notes' => ['sometimes', 'boolean'],
            ],
            'order' => [
                'returns\.window_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
                'order\.cancellation_window_hours' => ['sometimes', 'integer', 'min:0', 'max:8760'],
            ],
            'inventory' => [
                'inventory\.allow_negative_stock' => ['sometimes', 'boolean'],
                'inventory\.default_low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
                'inventory\.reserve_on_checkout' => ['sometimes', 'boolean'],
            ],
            'payment' => [
                'payment\.default_gateway' => ['sometimes', 'nullable', 'string', 'max:100'],
                'payment\.timeout_minutes' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            ],
            'pos' => [
                'pos\.default_warehouse_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'pos\.receipt_prefix' => ['sometimes', 'string', 'max:50'],
                'pos\.require_customer' => ['sometimes', 'boolean'],
            ],
            'delivery' => [
                'delivery\.assignment_strategy' => ['sometimes', 'string', Rule::in(['manual', 'automatic'])],
                'delivery\.assignment_radius_km' => ['sometimes', 'numeric', 'min:0'],
                'delivery\.require_proof_of_delivery' => ['sometimes', 'boolean'],
            ],
            'store_status' => [
                'store\.status' => ['sometimes', 'string', Rule::in(['open', 'closed', 'maintenance'])],
                'store\.maintenance_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ],
            'ecommerce' => [
                'is_marketplace_enabled' => ['sometimes', 'boolean'],
                'marketplace\.commission_type' => ['sometimes', 'string', Rule::enum(CommissionType::class)],
                'marketplace\.commission_rate' => ['sometimes', 'numeric', 'min:0'],
                'marketplace\.commission_fixed_amount' => ['sometimes', 'numeric', 'min:0'],
                'marketplace\.refund_window_days' => ['sometimes', 'integer', 'min:0'],
                'seller\.allow_registration' => ['sometimes', 'boolean'],
            ],
            'content' => [
                'content\.blog_enabled' => ['sometimes', 'boolean'],
                'content\.pages_enabled' => ['sometimes', 'boolean'],
            ],
            'customer' => [
                'customer\.registration_enabled' => ['sometimes', 'boolean'],
                'customer\.approval_required' => ['sometimes', 'boolean'],
                'customer\.default_group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            ],
            'notification' => [
                'notification\.email_enabled' => ['sometimes', 'boolean'],
                'notification\.sms_enabled' => ['sometimes', 'boolean'],
                'notification\.push_enabled' => ['sometimes', 'boolean'],
            ],
            'pricing' => [
                'pricing\.tax_inclusive' => ['sometimes', 'boolean'],
                'pricing\.display_includes_tax' => ['sometimes', 'boolean'],
            ],
            'tax' => [
                'tax\.enabled' => ['sometimes', 'boolean'],
            ],
            'shipping' => [
                'shipping\.enabled' => ['sometimes', 'boolean'],
                'shipping\.free_shipping_minimum' => ['sometimes', 'numeric', 'min:0'],
            ],
            default => throw ValidationException::withMessages([
                'domain' => ['Unknown settings domain ['.$domain.'].'],
            ]),
        };
    }

    /**
     * Validated settings keyed by commerce setting name (excludes empty store domain).
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $domain = (string) $this->route('domain');

        if ($domain === 'store') {
            return [];
        }

        if (! array_key_exists($domain, CommerceSettingService::DOMAINS)) {
            return [];
        }

        return $this->validated();
    }
}
