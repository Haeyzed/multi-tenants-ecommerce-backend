<?php

declare(strict_types=1);

use App\Enums\Tenant\Driver\DriverStatus;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Driver\DriverAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_16_120000_create_drivers_table.php',
        '2026_08_16_120001_create_driver_password_reset_tokens_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('driver can login and inactive or blocked drivers cannot', function (): void {
    $active = Driver::factory()->create([
        'email' => 'active-driver@example.com',
        'password' => 'Password1!',
    ]);

    $result = app(DriverAuthService::class)->login([
        'email' => 'active-driver@example.com',
        'password' => 'Password1!',
    ]);

    expect($result['driver']->is($active))->toBeTrue()
        ->and($result['token'])->not->toBeEmpty()
        ->and($active->fresh()->last_login_at)->not->toBeNull();

    Driver::factory()->inactive()->create([
        'email' => 'inactive-driver@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(DriverAuthService::class)->login([
        'email' => 'inactive-driver@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);

    Driver::factory()->blocked()->create([
        'email' => 'blocked-driver@example.com',
        'password' => 'Password1!',
    ]);

    expect(fn () => app(DriverAuthService::class)->login([
        'email' => 'blocked-driver@example.com',
        'password' => 'Password1!',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(DriverAuthService::class)->login([
        'email' => 'active-driver@example.com',
        'password' => 'WrongPassword1!',
    ]))->toThrow(ValidationException::class);
});

test('driver logout revokes the current token', function (): void {
    $driver = Driver::factory()->create();
    $token = $driver->createToken('api');
    $driver->withAccessToken($token->accessToken);

    expect($driver->tokens()->count())->toBe(1);

    app(DriverAuthService::class)->logout($driver);

    expect($driver->tokens()->count())->toBe(0);
});

test('forgot password is generic and reset password works', function (): void {
    Event::fake([PasswordResetRequested::class, PasswordChanged::class]);

    $driver = Driver::factory()->create(['email' => 'reset-driver@example.com']);

    app(DriverAuthService::class)->forgotPassword('missing@example.com');
    Event::assertNotDispatched(PasswordResetRequested::class);

    app(DriverAuthService::class)->forgotPassword('reset-driver@example.com');
    Event::assertDispatched(PasswordResetRequested::class);

    $token = Password::broker('drivers')->createToken($driver);

    app(DriverAuthService::class)->resetPassword([
        'email' => 'reset-driver@example.com',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
        'token' => $token,
    ]);

    expect(Hash::check('NewPassword1!', $driver->fresh()->password))->toBeTrue();

    Event::assertDispatched(PasswordChanged::class);
});

test('driver guard authenticates drivers', function (): void {
    $driver = Driver::factory()->create();

    Sanctum::actingAs($driver, ['*'], 'driver');

    expect(auth('driver')->user())->toBeInstanceOf(Driver::class)
        ->and(auth('driver')->id())->toBe($driver->id)
        ->and($driver->isLoginAllowed())->toBeTrue()
        ->and($driver->status)->toBe(DriverStatus::Active);
});
