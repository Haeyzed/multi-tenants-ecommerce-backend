<?php

declare(strict_types=1);

use App\Models\Tenant\User;
use App\Services\Tenant\Auth\AuthService;
use App\Services\Tenant\User\UserService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('tenant user can register and receive a token', function (): void {
    $result = app(AuthService::class)->register([
        'first_name' => 'Tenant',
        'last_name' => 'User',
        'email' => 'tenant@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    expect($result)->toHaveKeys(['user', 'token'])
        ->and($result['user'])->toBeInstanceOf(User::class)
        ->and($result['user']->email)->toBe('tenant@example.com')
        ->and($result['token'])->toBeString()->not->toBeEmpty();
});

test('tenant admin can list users via service', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    Sanctum::actingAs($admin, ['*'], 'tenant');

    $users = app(UserService::class)->list([]);

    expect($users->total())->toBeGreaterThanOrEqual(1);
});
