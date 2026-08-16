<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSellerUser;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Marketplace\SellerOfferService;
use App\Services\Tenant\User\UserService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_15_070002_add_seller_id_to_users_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
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

test('linking a user to a seller sets seller_id and assigns seller role', function (): void {
    $seller = Seller::factory()->sellable()->create();
    $user = User::factory()->create();

    $linked = app(UserService::class)->linkToSeller($user, $seller);

    expect($linked->seller_id)->toBe($seller->id)
        ->and($linked->isSellerUser())->toBeTrue()
        ->and($linked->hasRole('seller'))->toBeTrue();
});

test('creating a user with seller_id assigns the seller role', function (): void {
    $seller = Seller::factory()->sellable()->create();

    $user = app(UserService::class)->store([
        'first_name' => 'Sell',
        'last_name' => 'Staff',
        'email' => 'seller-staff@example.com',
        'password' => 'Password1!',
        'seller_id' => $seller->id,
    ]);

    expect($user->seller_id)->toBe($seller->id)
        ->and($user->hasRole('seller'))->toBeTrue();
});

test('EnsureSellerUser rejects users without a seller_id', function (): void {
    $user = User::factory()->create(['seller_id' => null]);

    Auth::guard('tenant')->setUser($user);

    $response = app(EnsureSellerUser::class)->handle(
        Request::create('/api/seller/profile', 'GET'),
        fn () => response('ok'),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toContain('not linked to a seller');
});

test('EnsureSellerUser allows linked seller users', function (): void {
    $seller = Seller::factory()->sellable()->create();
    $user = User::factory()->create(['seller_id' => $seller->id]);
    $user->assignRole('seller');

    Auth::guard('tenant')->setUser($user);

    $request = Request::create('/api/seller/profile', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = app(EnsureSellerUser::class)->handle(
        $request,
        fn () => response('ok'),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

test('seller isolation prevents managing another sellers offer', function (): void {
    $sellerA = Seller::factory()->sellable()->create();
    $sellerB = Seller::factory()->sellable()->create();
    $product = Product::factory()->active()->create();
    Warehouse::factory()->create(['is_default' => true]);

    $offer = app(SellerOfferService::class)->store([
        'seller_id' => $sellerA->id,
        'product_id' => $product->id,
        'price' => '50.00',
    ]);

    $actor = app(UserService::class)->linkToSeller(User::factory()->create(), $sellerB);

    expect(fn () => app(SellerOfferService::class)->update($offer, ['price' => '40.00'], $actor))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SellerOfferService::class)->destroy($offer, $actor))
        ->toThrow(ValidationException::class);

    $list = app(SellerOfferService::class)->list([], $actor);

    expect($list->total())->toBe(0);
});
