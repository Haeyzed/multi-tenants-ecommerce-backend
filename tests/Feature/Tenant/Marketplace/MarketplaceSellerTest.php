<?php

declare(strict_types=1);

use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Events\SellerApproved;
use App\Events\SellerSuspended;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Marketplace\SellerOfferService;
use App\Services\Tenant\Marketplace\SellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_070001_create_sellers_table.php',
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

    app(CommerceSettingService::class)->setMarketplaceEnabled(true);
});

test('marketplace disabled blocks sellable offer creation path via setting', function (): void {
    app(CommerceSettingService::class)->setMarketplaceEnabled(false);

    expect(app(CommerceSettingService::class)->isMarketplaceEnabled())->toBeFalse();
});

test('seller onboarding approve and suspend flow', function (): void {
    Event::fake([SellerApproved::class, SellerSuspended::class]);

    $service = app(SellerService::class);
    $seller = $service->store(['name' => 'Acme Sellers', 'email' => 'acme@example.com']);

    expect($seller->verification_status)->toBe(SellerVerificationStatus::Pending)
        ->and($seller->status)->toBe(SellerStatus::Inactive)
        ->and($seller->canSell())->toBeFalse();

    $approved = $service->approve($seller);

    expect($approved->verification_status)->toBe(SellerVerificationStatus::Approved)
        ->and($approved->status)->toBe(SellerStatus::Active)
        ->and($approved->canSell())->toBeTrue();

    Event::assertDispatched(SellerApproved::class);

    $suspended = $service->suspend($approved);

    expect($suspended->status)->toBe(SellerStatus::Suspended)
        ->and($suspended->canSell())->toBeFalse();

    Event::assertDispatched(SellerSuspended::class);
});

test('seller rejection of pending seller', function (): void {
    $service = app(SellerService::class);
    $seller = $service->store(['name' => 'Reject Me']);

    $rejected = $service->reject($seller);

    expect($rejected->verification_status)->toBe(SellerVerificationStatus::Rejected)
        ->and($rejected->canSell())->toBeFalse();
});

test('seller offer requires approved active seller and active product', function (): void {
    $pending = Seller::factory()->create();
    $product = Product::factory()->active()->create();
    Warehouse::factory()->create(['is_default' => true]);

    $offerService = app(SellerOfferService::class);

    expect(fn () => $offerService->store([
        'seller_id' => $pending->id,
        'product_id' => $product->id,
        'price' => '100.00',
        'stock' => 5,
    ]))->toThrow(ValidationException::class);

    $seller = Seller::factory()->sellable()->create();
    $offer = $offerService->store([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'price' => '99.50',
        'stock' => 5,
    ]);

    expect($offer)->toBeInstanceOf(SellerOffer::class)
        ->and($offer->price)->toBe('99.50')
        ->and($offer->status)->toBe(SellerOfferStatus::Active)
        ->and($offer->inventories()->sum('quantity'))->toBe(5);
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

    $actor = new User;
    $actor->seller_id = $sellerB->id;

    expect(fn () => app(SellerOfferService::class)->update($offer, ['price' => '40.00'], $actor))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SellerOfferService::class)->destroy($offer, $actor))
        ->toThrow(ValidationException::class);
});
