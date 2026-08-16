<?php

declare(strict_types=1);

use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Settings\TenantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_15_060001_create_commerce_settings_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);
});

test('commerce settings domain returns defaults and updates allowlisted keys', function (): void {
    $commerce = app(CommerceSettingService::class);

    $checkout = $commerce->getDomain('checkout');

    expect($checkout['checkout.guest_checkout'])->toBeFalse()
        ->and($checkout['checkout.minimum_order_amount'])->toBe('0')
        ->and($checkout['checkout.allow_order_notes'])->toBeTrue();

    $updated = $commerce->updateDomain('checkout', [
        'checkout.guest_checkout' => true,
        'checkout.minimum_order_amount' => '25.50',
        'checkout.require_phone' => true,
    ]);

    expect($updated['checkout.guest_checkout'])->toBeTrue()
        ->and($updated['checkout.minimum_order_amount'])->toBe('25.50')
        ->and($updated['checkout.require_phone'])->toBeTrue()
        ->and($commerce->get('checkout.guest_checkout'))->toBe('true');
});

test('delivery domain syncs assignment config overrides', function (): void {
    $commerce = app(CommerceSettingService::class);

    Config::set('delivery.assignment.strategy', 'manual');
    Config::set('delivery.assignment.radius_km', 15);

    $commerce->updateDomain('delivery', [
        'delivery.assignment_strategy' => 'automatic',
        'delivery.assignment_radius_km' => 42.5,
    ]);

    expect(config('delivery.assignment.strategy'))->toBe('automatic')
        ->and((float) config('delivery.assignment.radius_km'))->toBe(42.5)
        ->and($commerce->deliveryAssignmentStrategy())->toBe('automatic')
        ->and($commerce->deliveryAssignmentRadiusKm())->toBe(42.5);
});

test('unknown domain keys are rejected', function (): void {
    $commerce = app(CommerceSettingService::class);

    expect(fn () => $commerce->updateDomain('checkout', [
        'checkout.secret_key' => 'nope',
    ]))->toThrow(ValidationException::class);
});

test('tenant settings service exposes store snapshot and writable domains', function (): void {
    $settings = app(TenantSettingsService::class);

    $store = $settings->getDomain('store');
    expect($store)->toHaveKeys(['display_name', 'currency', 'timezone', 'language', 'logo_url']);

    $order = $settings->updateDomain('order', [
        'returns.window_days' => 21,
        'order.cancellation_window_hours' => 12,
    ]);

    expect($order['returns.window_days'])->toBe(21)
        ->and($order['order.cancellation_window_hours'])->toBe(12)
        ->and(app(CommerceSettingService::class)->returnWindowDays())->toBe(21)
        ->and(app(CommerceSettingService::class)->orderCancellationWindowHours())->toBe(12);

    expect(fn () => $settings->updateDomain('store', ['display_name' => 'Hack']))
        ->toThrow(ValidationException::class);
});

test('existing marketplace cart loyalty and tax helpers keep working', function (): void {
    $commerce = app(CommerceSettingService::class);

    $commerce->setMarketplaceEnabled(true);
    $commerce->set('marketplace.commission_rate', '15');
    $commerce->set('cart.abandon_after_hours', '48');
    $commerce->set('loyalty.is_active', 'false');
    $commerce->set('tax_rate', '7.5');

    expect($commerce->isMarketplaceEnabled())->toBeTrue()
        ->and($commerce->defaultCommissionRate())->toBe('15')
        ->and($commerce->cartAbandonAfterHours())->toBe(48)
        ->and($commerce->loyaltyIsActive())->toBeFalse()
        ->and($commerce->taxRate())->toBe('7.5');
});

test('customer domain returns defaults and updates allowlisted keys', function (): void {
    $commerce = app(CommerceSettingService::class);

    $customer = $commerce->getDomain('customer');

    expect($customer['customer.registration_enabled'])->toBeTrue()
        ->and($customer['customer.approval_required'])->toBeFalse()
        ->and($customer['customer.default_group_id'])->toBeNull();

    $updated = $commerce->updateDomain('customer', [
        'customer.registration_enabled' => false,
        'customer.approval_required' => true,
        'customer.default_group_id' => 3,
    ]);

    expect($updated['customer.registration_enabled'])->toBeFalse()
        ->and($updated['customer.approval_required'])->toBeTrue()
        ->and($updated['customer.default_group_id'])->toBe(3);
});
