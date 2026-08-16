<?php

declare(strict_types=1);

use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Events\SellerRegistered;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Marketplace\SellerOfferService;
use App\Services\Tenant\Seller\SellerAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Selective tenant migrates: SQLite DDL is not rolled back with RefreshDatabase
    // transactions, so only run once per process when tables are missing.
    if (! Schema::hasTable('sellers')) {
        foreach ([
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
            '2026_08_16_160758_make_sellers_authenticatable_table.php',
            '2026_08_16_160812_create_seller_password_reset_tokens_table.php',
        ] as $file) {
            $this->artisan('migrate', [
                '--path' => database_path('migrations/tenant/'.$file),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    } elseif (! Schema::hasColumn('sellers', 'password')) {
        foreach ([
            '2026_08_16_160758_make_sellers_authenticatable_table.php',
            '2026_08_16_160812_create_seller_password_reset_tokens_table.php',
        ] as $file) {
            $this->artisan('migrate', [
                '--path' => database_path('migrations/tenant/'.$file),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    app(CommerceSettingService::class)->setMarketplaceEnabled(true);
    app(CommerceSettingService::class)->set('seller.allow_registration', 'true');
});

test('seller can register when registration is enabled', function (): void {
    $result = app(SellerAuthService::class)->register([
        'name' => 'New Vendor',
        'email' => 'vendor@example.com',
        'password' => 'Password1!',
    ]);

    expect($result['seller'])->toBeInstanceOf(Seller::class)
        ->and($result['token'])->not->toBeEmpty()
        ->and($result['seller']->status)->toBe(SellerStatus::Inactive)
        ->and($result['seller']->verification_status)->toBe(SellerVerificationStatus::Pending)
        ->and(Hash::check('Password1!', $result['seller']->password))->toBeTrue();
});

test('seller registration is blocked when disabled', function (): void {
    app(CommerceSettingService::class)->set('seller.allow_registration', 'false');

    expect(fn () => app(SellerAuthService::class)->register([
        'name' => 'Blocked Vendor',
        'email' => 'blocked-vendor@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);
});

test('seller can login and suspended or rejected sellers cannot', function (): void {
    $active = Seller::factory()->sellable()->create([
        'email' => 'active-seller@example.com',
        'password' => 'Password1!',
    ]);

    $result = app(SellerAuthService::class)->login([
        'email' => 'active-seller@example.com',
        'password' => 'Password1!',
    ]);

    expect($result['seller']->is($active))->toBeTrue()
        ->and($result['token'])->not->toBeEmpty()
        ->and($active->fresh()->last_login_at)->not->toBeNull();

    Seller::factory()->suspended()->create([
        'email' => 'suspended-seller@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(SellerAuthService::class)->login([
        'email' => 'suspended-seller@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);

    Seller::factory()->rejected()->create([
        'email' => 'rejected-seller@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(SellerAuthService::class)->login([
        'email' => 'rejected-seller@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);
});

test('seller logout revokes the current token', function (): void {
    $seller = Seller::factory()->sellable()->create();
    $token = $seller->createToken('api');
    $seller->withAccessToken($token->accessToken);

    expect($seller->tokens()->count())->toBe(1);

    app(SellerAuthService::class)->logout($seller);

    expect($seller->tokens()->count())->toBe(0);
});

test('forgot password is generic and reset password works', function (): void {
    Event::fake([PasswordResetRequested::class, PasswordChanged::class]);

    $seller = Seller::factory()->sellable()->create(['email' => 'reset-seller@example.com']);

    app(SellerAuthService::class)->forgotPassword('missing@example.com');
    Event::assertNotDispatched(PasswordResetRequested::class);

    app(SellerAuthService::class)->forgotPassword('reset-seller@example.com');
    Event::assertDispatched(PasswordResetRequested::class);

    $token = Password::broker('sellers')->createToken($seller);

    app(SellerAuthService::class)->resetPassword([
        'email' => 'reset-seller@example.com',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
        'token' => $token,
    ]);

    expect(Hash::check('NewPassword1!', $seller->fresh()->password))->toBeTrue();

    Event::assertDispatched(PasswordChanged::class);
});

test('seller guard authenticates sellers', function (): void {
    $seller = Seller::factory()->sellable()->create();

    Sanctum::actingAs($seller, ['*'], 'seller');

    expect(auth('seller')->user())->toBeInstanceOf(Seller::class)
        ->and(auth('seller')->id())->toBe($seller->id)
        ->and($seller->isLoginAllowed())->toBeTrue();
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

    expect(fn () => app(SellerOfferService::class)->update($offer, ['price' => '40.00'], $sellerB))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SellerOfferService::class)->destroy($offer, $sellerB))
        ->toThrow(ValidationException::class);

    $list = app(SellerOfferService::class)->list([], $sellerB);

    expect($list->total())->toBe(0);
});

test('seller registration fires registered event', function (): void {
    Event::fake([SellerRegistered::class]);

    app(SellerAuthService::class)->register([
        'name' => 'Event Vendor',
        'email' => 'event-vendor@example.com',
        'password' => 'Password1!',
    ]);

    Event::assertDispatched(SellerRegistered::class);
});

test('seller can update profile and change password', function (): void {
    Event::fake([PasswordChanged::class]);

    $seller = Seller::factory()->sellable()->create([
        'email' => 'profile-seller@example.com',
        'password' => 'Password1!',
    ]);

    $updated = app(SellerAuthService::class)->updateProfile($seller, [
        'name' => 'Updated Seller Co',
        'description' => 'Updated bio',
        'phone' => '+15551212',
    ]);

    expect($updated->name)->toBe('Updated Seller Co')
        ->and($updated->description)->toBe('Updated bio')
        ->and($updated->phone)->toBe('+15551212');

    app(SellerAuthService::class)->changePassword($seller, [
        'current_password' => 'Password1!',
        'password' => 'NewPassword1!',
    ]);

    expect(Hash::check('NewPassword1!', $seller->fresh()->password))->toBeTrue()
        ->and($seller->tokens()->count())->toBe(0);

    Event::assertDispatched(PasswordChanged::class);
});

test('seller can replace and remove logo', function (): void {
    Storage::fake('public');

    $seller = Seller::factory()->sellable()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

    $media = app(SellerAuthService::class)->replaceLogo($seller, $file);

    expect($media->collection_name)->toBe('logo')
        ->and($seller->fresh()->logo_url)->not->toBeEmpty();

    app(SellerAuthService::class)->removeLogo($seller);

    expect($seller->fresh()->logo_url)->toBeNull();
});

test('users table no longer has seller_id', function (): void {
    expect(Schema::hasColumn('users', 'seller_id'))->toBeFalse()
        ->and((new User)->getFillable())->not->toContain('seller_id');
});

test('authenticated seller can list own offers via offer service without seller_id payload', function (): void {
    $seller = Seller::factory()->sellable()->create();
    $product = Product::factory()->active()->create();
    Warehouse::factory()->create(['is_default' => true]);

    $offer = app(SellerOfferService::class)->store([
        'product_id' => $product->id,
        'price' => '25.00',
    ], $seller);

    expect($offer->seller_id)->toBe($seller->id)
        ->and(app(SellerOfferService::class)->list([], $seller)->total())->toBe(1);
});
