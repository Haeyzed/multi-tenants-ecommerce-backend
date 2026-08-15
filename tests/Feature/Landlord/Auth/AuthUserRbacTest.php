<?php

declare(strict_types=1);

use App\Models\Landlord\User;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

/**
 * Create an authenticated landlord user with the given roles/permissions.
 *
 * @param  list<string>  $roles
 * @param  list<string>  $permissions
 */
function landlordUser(array $roles = ['admin'], array $permissions = [], array $overrides = []): User
{
    $user = User::factory()->create($overrides);

    if ($roles !== []) {
        $user->syncRoles($roles);
    }

    if ($permissions !== []) {
        $user->syncPermissions($permissions);
    }

    return $user;
}

test('landlord can login with valid credentials', function (): void {
    $user = landlordUser(['admin'], [], [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

test('landlord login fails with invalid credentials', function (): void {
    landlordUser(['admin'], [], ['email' => 'admin@example.com']);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

test('landlord can logout and revoke the current token', function (): void {
    $user = landlordUser();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('landlord forgot password returns a generic success message', function (): void {
    Notification::fake();
    landlordUser(['admin'], [], ['email' => 'reset@example.com']);

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'reset@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('landlord can reset password with a valid token', function (): void {
    Notification::fake();
    $user = landlordUser(['admin'], [], ['email' => 'reset@example.com']);
    $token = Password::broker('landlord_users')->createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $token,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

test('landlord can change password', function (): void {
    $user = landlordUser(['admin'], [], ['password' => 'password']);
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'password',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

test('landlord can update profile and avatar', function (): void {
    Storage::fake('public');

    $user = landlordUser();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->post('/api/auth/profile', [
        '_method' => 'PUT',
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'phone' => '+15551234567',
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Updated');

    expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

test('landlord admin can list and create users', function (): void {
    $admin = landlordUser(['admin']);
    Sanctum::actingAs($admin, ['*'], 'landlord');

    $this->getJson('/api/users')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->postJson('/api/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com');
});

test('landlord user without permission cannot list users', function (): void {
    $user = landlordUser([]);
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->getJson('/api/users')->assertForbidden();
});

test('landlord admin can manage roles and permissions', function (): void {
    $admin = landlordUser(['admin']);
    Sanctum::actingAs($admin, ['*'], 'landlord');

    $this->postJson('/api/roles', [
        'name' => 'editor',
        'permissions' => ['users.view', 'users.show'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'editor');

    $role = Role::findByName('editor', 'landlord');

    $this->putJson('/api/roles/'.$role->id.'/permissions', [
        'permissions' => ['users.view'],
    ])->assertOk();

    $this->getJson('/api/permissions')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Permission::where('guard_name', 'landlord')->count())->toBeGreaterThan(0);
});

test('landlord can sync roles onto a user', function (): void {
    $admin = landlordUser(['admin']);
    $target = landlordUser([]);
    Sanctum::actingAs($admin, ['*'], 'landlord');

    $this->putJson('/api/users/'.$target->id.'/roles', [
        'roles' => ['admin'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($target->fresh()->hasRole('admin'))->toBeTrue();
});
