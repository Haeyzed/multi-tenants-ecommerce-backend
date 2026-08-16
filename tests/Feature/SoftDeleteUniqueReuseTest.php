<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\CouponType;
use App\Http\Requests\Tenant\Commerce\StoreCouponRequest;
use App\Http\Requests\Tenant\Customer\Auth\RegisterRequest;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach ([
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_090101_create_coupons_table.php',
    ] as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('soft-deleted coupon codes can be reissued', function (): void {
    $coupon = Coupon::query()->create([
        'code' => 'SAVE10',
        'name' => 'Save 10',
        'type' => CouponType::Fixed,
        'value' => '10.00',
        'minimum_order_amount' => '0.00',
        'is_active' => true,
    ]);

    $coupon->delete();

    expect(Coupon::withTrashed()->find($coupon->id)?->code)->toStartWith('SAVE10__d')
        ->and(Validator::make([
            'code' => 'SAVE10',
            'name' => 'Save 10 again',
            'type' => CouponType::Fixed->value,
            'value' => '10.00',
        ], (new StoreCouponRequest)->rules())->passes())->toBeTrue();

    $reissued = Coupon::query()->create([
        'code' => 'SAVE10',
        'name' => 'Save 10 again',
        'type' => CouponType::Fixed,
        'value' => '10.00',
        'minimum_order_amount' => '0.00',
        'is_active' => true,
    ]);

    expect($reissued->code)->toBe('SAVE10');
});

test('soft-deleted customer emails can be re-registered', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'reuse@example.com',
        'phone' => '+15550001111',
    ]);

    $customer->delete();

    expect(Customer::withTrashed()->find($customer->id)?->email)->toStartWith('deleted+')
        ->and(Validator::make([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'reuse@example.com',
            'phone' => '+15550001111',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ], (new RegisterRequest)->rules())->passes())->toBeTrue();

    $reissued = Customer::factory()->create([
        'email' => 'reuse@example.com',
        'phone' => '+15550001111',
    ]);

    expect($reissued->email)->toBe('reuse@example.com');
});
