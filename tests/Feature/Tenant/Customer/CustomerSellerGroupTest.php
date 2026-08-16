<?php

declare(strict_types=1);

use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerGroup;
use App\Models\Tenant\User;
use App\Policies\Tenant\CustomerGroupPolicy;
use App\Policies\Tenant\SellerGroupPolicy;
use App\Services\Tenant\Customer\CustomerGroupService;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use App\Services\Tenant\Marketplace\SellerGroupService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_100402_create_customer_segments_table.php',
        '2026_08_16_110000_create_customer_groups_table.php',
        '2026_08_16_110001_create_seller_groups_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('customer group service creates updates lists and deletes groups', function (): void {
    $service = app(CustomerGroupService::class);

    $group = $service->store([
        'name' => 'VIP',
        'description' => 'High value',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($group->slug)->toBe('vip');

    $updated = $service->update($group, ['name' => 'VIP Plus']);
    expect($updated->name)->toBe('VIP Plus');

    expect($service->list(['search' => 'VIP'])->total())->toBe(1);

    $service->destroy($updated);
    expect(CustomerGroup::query()->whereKey($group->id)->exists())->toBeFalse();
});

test('customer group cannot be deleted while customers are assigned', function (): void {
    $group = CustomerGroup::factory()->create();
    Customer::factory()->create(['customer_group_id' => $group->id]);

    expect(fn () => app(CustomerGroupService::class)->destroy($group))
        ->toThrow(ValidationException::class);
});

test('customer segment crud stores rules', function (): void {
    $service = app(CustomerSegmentationService::class);

    $segment = $service->store([
        'name' => 'Returners',
        'match' => 'all',
        'conditions' => [
            ['type' => CustomerSegmentRule::ReturningCustomer->value],
        ],
    ]);

    expect($segment->slug)->toBe('returners')
        ->and($segment->conditions())->toHaveCount(1);

    $updated = $service->update($segment, [
        'description' => 'Customers with orders',
        'is_active' => true,
    ]);

    expect($updated->description)->toBe('Customers with orders');

    $service->destroy($updated);
    expect(CustomerSegment::query()->whereKey($segment->id)->exists())->toBeFalse();
});

test('seller group service creates and blocks delete with members', function (): void {
    $service = app(SellerGroupService::class);

    $group = $service->store([
        'name' => 'Gold Sellers',
        'commission_rate' => '12.5',
    ]);

    expect($group->name)->toBe('Gold Sellers');

    Seller::factory()->create(['seller_group_id' => $group->id]);

    expect(fn () => $service->destroy($group))
        ->toThrow(ValidationException::class);
});

test('customer and seller group policies respect rbac permissions', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    Sanctum::actingAs($admin, ['*'], 'tenant');

    $customerGroup = CustomerGroup::factory()->create();
    $sellerGroup = SellerGroup::factory()->create();

    expect(app(CustomerGroupPolicy::class)->viewAny($admin))->toBeTrue()
        ->and(app(CustomerGroupPolicy::class)->create($admin))->toBeTrue()
        ->and(app(CustomerGroupPolicy::class)->delete($admin, $customerGroup))->toBeTrue()
        ->and(app(SellerGroupPolicy::class)->viewAny($admin))->toBeTrue()
        ->and(app(SellerGroupPolicy::class)->create($admin))->toBeTrue()
        ->and(app(SellerGroupPolicy::class)->delete($admin, $sellerGroup))->toBeTrue();

    $viewer = User::factory()->create();
    expect(app(CustomerGroupPolicy::class)->create($viewer))->toBeFalse()
        ->and(app(SellerGroupPolicy::class)->create($viewer))->toBeFalse();
});
