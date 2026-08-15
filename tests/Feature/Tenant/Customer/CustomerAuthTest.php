<?php

declare(strict_types=1);

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Customer\CustomerStatus;
use App\Events\CustomerEmailVerificationRequested;
use App\Events\CustomerRegistered;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\User;
use App\Services\Tenant\Customer\CustomerAuthService;
use App\Services\Tenant\Customer\CustomerService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_041619_create_customer_password_reset_tokens_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    Storage::fake('public');
    config([
        'media-library.disk_name' => 'public',
        'media-library.queue_conversions_by_default' => false,
        'notifications.queue' => false,
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
    ]);
});

test('customer can register and password is hashed', function (): void {
    Event::fake([CustomerRegistered::class, CustomerEmailVerificationRequested::class]);

    $result = app(CustomerAuthService::class)->register([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ]);

    expect($result)->toHaveKeys(['customer', 'token'])
        ->and($result['customer'])->toBeInstanceOf(Customer::class)
        ->and($result['customer']->email)->toBe('jane@example.com')
        ->and($result['token'])->toBeString()->not->toBeEmpty()
        ->and(Hash::check('Password1!', $result['customer']->password))->toBeTrue()
        ->and($result['customer']->password)->not->toBe('Password1!');

    Event::assertDispatched(CustomerRegistered::class);
    Event::assertDispatched(CustomerEmailVerificationRequested::class);
});

test('duplicate email within tenant is rejected', function (): void {
    Customer::factory()->create(['email' => 'dup@example.com']);

    expect(fn () => Customer::factory()->create(['email' => 'dup@example.com']))
        ->toThrow(Exception::class);
});

test('customer can login and inactive or blocked customers cannot', function (): void {
    $active = Customer::factory()->create([
        'email' => 'active@example.com',
        'password' => 'Password1!',
    ]);

    $result = app(CustomerAuthService::class)->login([
        'email' => 'active@example.com',
        'password' => 'Password1!',
    ]);

    expect($result['customer']->is($active))->toBeTrue()
        ->and($result['token'])->not->toBeEmpty()
        ->and($active->fresh()->last_login_at)->not->toBeNull();

    Customer::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(CustomerAuthService::class)->login([
        'email' => 'inactive@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);

    Customer::factory()->blocked()->create([
        'email' => 'blocked@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(CustomerAuthService::class)->login([
        'email' => 'blocked@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(CustomerAuthService::class)->login([
        'email' => 'active@example.com',
        'password' => 'WrongPassword1!',
    ]))->toThrow(ValidationException::class);
});

test('customer logout revokes the current token', function (): void {
    $customer = Customer::factory()->create();
    $token = $customer->createToken('api');
    $customer->withAccessToken($token->accessToken);

    expect($customer->tokens()->count())->toBe(1);

    app(CustomerAuthService::class)->logout($customer);

    expect($customer->tokens()->count())->toBe(0);
});

test('forgot password is generic and reset password works', function (): void {
    Event::fake([PasswordResetRequested::class, PasswordChanged::class]);

    $customer = Customer::factory()->create(['email' => 'reset@example.com']);

    app(CustomerAuthService::class)->forgotPassword('missing@example.com');
    Event::assertNotDispatched(PasswordResetRequested::class);

    app(CustomerAuthService::class)->forgotPassword('reset@example.com');
    Event::assertDispatched(PasswordResetRequested::class);

    $token = Password::broker('customers')->createToken($customer);

    app(CustomerAuthService::class)->resetPassword([
        'email' => 'reset@example.com',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
        'token' => $token,
    ]);

    expect(Hash::check('NewPassword1!', $customer->fresh()->password))->toBeTrue();

    Event::assertDispatched(PasswordChanged::class);

    expect(fn () => app(CustomerAuthService::class)->resetPassword([
        'email' => 'reset@example.com',
        'password' => 'AnotherPassword1!',
        'password_confirmation' => 'AnotherPassword1!',
        'token' => 'invalid-token',
    ]))->toThrow(ValidationException::class);
});

test('customer can change password and tokens are revoked', function (): void {
    Event::fake([PasswordChanged::class]);

    $customer = Customer::factory()->create(['password' => 'Password1!']);
    $customer->createToken('api');

    expect($customer->tokens()->count())->toBe(1);

    app(CustomerAuthService::class)->changePassword($customer, [
        'current_password' => 'Password1!',
        'password' => 'ChangedPassword1!',
    ]);

    expect(Hash::check('ChangedPassword1!', $customer->fresh()->password))->toBeTrue()
        ->and($customer->tokens()->count())->toBe(0);

    Event::assertDispatched(PasswordChanged::class);

    expect(fn () => app(CustomerAuthService::class)->changePassword($customer->fresh(), [
        'current_password' => 'Password1!',
        'password' => 'AgainPassword1!',
    ]))->toThrow(ValidationException::class);
});

test('customer can verify email and resend is throttled', function (): void {
    $customer = Customer::factory()->unverified()->create();

    $token = 'verify-token-example';
    Cache::put('customer.email_verify.'.$customer->id, hash('sha256', $token), now()->addHour());

    app(CustomerAuthService::class)->verifyEmail($customer, $token);

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();

    expect(fn () => app(CustomerAuthService::class)->verifyEmail($customer->fresh(), $token))
        ->toThrow(ValidationException::class);

    $unverified = Customer::factory()->unverified()->create();
    Event::fake([CustomerEmailVerificationRequested::class]);

    app(CustomerAuthService::class)->resendVerification($unverified);
    Event::assertDispatched(CustomerEmailVerificationRequested::class);

    expect(fn () => app(CustomerAuthService::class)->resendVerification($unverified))
        ->toThrow(ValidationException::class);
});

test('customer can update profile and manage avatar', function (): void {
    $customer = Customer::factory()->create([
        'first_name' => 'Old',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    Event::fake([CustomerEmailVerificationRequested::class]);

    $updated = app(CustomerService::class)->updateProfile($customer, [
        'first_name' => 'New',
        'email' => 'new@example.com',
    ]);

    expect($updated->first_name)->toBe('New')
        ->and($updated->email)->toBe('new@example.com')
        ->and($updated->email_verified_at)->toBeNull();

    Event::assertDispatched(CustomerEmailVerificationRequested::class);

    $avatar = UploadedFile::fake()->image('avatar.jpg');
    $media = app(CustomerService::class)->replaceAvatar($customer->fresh(), $avatar);

    expect($media->collection_name)->toBe(MediaCollection::Avatar->value)
        ->and($customer->fresh()->avatar_url)->not->toBeNull();

    app(CustomerService::class)->removeAvatar($customer->fresh());

    expect($customer->fresh()->getFirstMedia(MediaCollection::Avatar->value))->toBeNull();
});

test('customer can manage addresses with a single default', function (): void {
    $customer = Customer::factory()->create();
    $service = app(CustomerService::class);

    $first = $service->storeAddress($customer, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'address_line_1' => '1 Main St',
    ]);

    expect($first->is_default)->toBeTrue();

    $second = $service->storeAddress($customer, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'address_line_1' => '2 Side St',
        'is_default' => true,
    ]);

    expect($second->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse()
        ->and($customer->addresses()->where('is_default', true)->count())->toBe(1);

    $service->makeDefault($customer, $first->fresh());

    expect($first->fresh()->is_default)->toBeTrue()
        ->and($second->fresh()->is_default)->toBeFalse();

    $other = Customer::factory()->create();
    $foreign = CustomerAddress::factory()->create(['customer_id' => $other->id]);

    expect(fn () => $service->updateAddress($customer, $foreign, ['address_line_1' => 'Hacked']))
        ->toThrow(ValidationException::class);
});

test('customer can deactivate account without hard delete', function (): void {
    $customer = Customer::factory()->create();
    $customer->createToken('api');

    app(CustomerService::class)->deactivateAccount($customer);

    $fresh = Customer::withTrashed()->find($customer->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->status)->toBe(CustomerStatus::Inactive)
        ->and($fresh->tokens()->count())->toBe(0)
        ->and(Customer::query()->find($customer->id))->toBeNull();
});

test('admin can list update and change customer status', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    Sanctum::actingAs($admin, ['*'], 'tenant');

    Customer::factory()->count(3)->create();

    $list = app(CustomerService::class)->list(['per_page' => 2]);

    expect($list->total())->toBe(3)
        ->and($list->count())->toBe(2);

    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $customer->createToken('api');

    $updated = app(CustomerService::class)->update($customer, [
        'first_name' => 'AdminUpdated',
    ]);

    expect($updated->first_name)->toBe('AdminUpdated');

    $blocked = app(CustomerService::class)->updateStatus($customer, CustomerStatus::Blocked);

    expect($blocked->status)->toBe(CustomerStatus::Blocked)
        ->and($customer->tokens()->count())->toBe(0);
});

test('customer guard authenticates customers not tenant users', function (): void {
    $customer = Customer::factory()->create();
    $tenantUser = User::factory()->create();

    Sanctum::actingAs($customer, ['*'], 'customer');

    expect(auth('customer')->user())->toBeInstanceOf(Customer::class)
        ->and(auth('customer')->id())->toBe($customer->id)
        ->and(auth('tenant')->user())->toBeNull();

    auth('customer')->forgetUser();
    Sanctum::actingAs($tenantUser, ['*'], 'tenant');

    expect(auth('tenant')->user())->toBeInstanceOf(User::class)
        ->and(auth('customer')->user())->toBeNull();
});
