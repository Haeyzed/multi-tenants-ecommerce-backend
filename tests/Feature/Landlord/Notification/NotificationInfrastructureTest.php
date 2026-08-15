<?php

declare(strict_types=1);

use App\DTO\Notification\NotificationPayload;
use App\Enums\Notification\NotificationChannel;
use App\Events\PasswordChanged;
use App\Events\PaymentSucceeded;
use App\Listeners\Notification\SendPasswordChangedNotification;
use App\Listeners\Notification\SendPaymentSucceededNotification;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use App\Models\Notification\DeviceToken;
use App\Models\Notification\NotificationPreference;
use App\Notifications\TemplatedNotification;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TemplateRenderer;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
        NotificationTemplateSeeder::class,
    ]);

    config(['notifications.queue' => false]);
});

/**
 * @param  list<string>  $roles
 */
function notifLandlord(array $roles = ['admin']): User
{
    $user = User::factory()->create();
    $user->syncRoles($roles);

    return $user;
}

test('landlord can crud and preview notification templates and rejects unknown placeholders', function (): void {
    $user = notifLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $create = $this->postJson('/api/notification-templates', [
        'key' => 'custom.welcome',
        'name' => 'Custom Welcome',
        'channels' => ['database', 'email'],
        'variables' => ['user_name'],
        'title' => 'Hello {{user_name}}',
        'body' => 'Welcome {{user_name}}',
        'email_subject' => 'Welcome {{user_name}}',
        'email_body' => 'Hello {{user_name}}',
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.key', 'custom.welcome');

    $id = $create->json('data.id');

    $this->getJson('/api/notification-templates')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->postJson("/api/notification-templates/{$id}/preview", [
        'data' => ['user_name' => 'Ada'],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Hello Ada');

    $this->postJson('/api/notification-templates', [
        'key' => 'custom.invalid',
        'name' => 'Invalid',
        'channels' => ['database'],
        'variables' => ['user_name'],
        'title' => 'Hello {{unknown_var}}',
    ])->assertUnprocessable();
});

test('notification service sends database notification and respects email preference', function (): void {
    Notification::fake();

    $user = notifLandlord();

    app(NotificationService::class)->sendNow($user, 'user.created', [
        'user_name' => 'Ada',
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, TemplatedNotification::class);

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_key' => 'user.created',
        'channel' => NotificationChannel::Email,
        'enabled' => false,
    ]);

    Notification::fake();

    app(NotificationService::class)->sendNow($user, 'user.created', [
        'user_name' => 'Ada',
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, TemplatedNotification::class, function (TemplatedNotification $notification) use ($user): bool {
        return $notification->via($user) === ['database'];
    });
});

test('device tokens can be registered listed and removed with uniqueness', function (): void {
    $user = notifLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->postJson('/api/devices', [
        'device_type' => 'android',
        'device_token' => 'token-abc',
        'app_version' => '1.0.0',
    ])->assertCreated()
        ->assertJsonPath('data.device_token', 'token-abc');

    $this->postJson('/api/devices', [
        'device_type' => 'android',
        'device_token' => 'token-abc',
    ])->assertCreated();

    expect(DeviceToken::query()->where('device_token', 'token-abc')->count())->toBe(1);

    $this->getJson('/api/devices')
        ->assertOk()
        ->assertJsonPath('data.0.device_token', 'token-abc');

    $deviceId = DeviceToken::query()->where('device_token', 'token-abc')->value('id');

    $this->deleteJson("/api/devices/{$deviceId}")
        ->assertOk();

    expect(DeviceToken::query()->whereKey($deviceId)->exists())->toBeFalse();
});

test('inbox is scoped to authenticated user', function (): void {
    $owner = notifLandlord();
    $other = notifLandlord();

    $owner->notifyNow(new TemplatedNotification(
        new NotificationPayload(
            key: 'user.created',
            data: ['user_name' => 'Owner'],
            channels: ['database'],
            content: ['title' => 'Welcome', 'body' => 'Hi'],
        ),
        ['database'],
    ));

    Sanctum::actingAs($other, ['*'], 'landlord');

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data', []);

    Sanctum::actingAs($owner, ['*'], 'landlord');

    $list = $this->getJson('/api/notifications')->assertOk();
    $notificationId = $list->json('data.0.id');

    expect($notificationId)->not->toBeNull();

    Sanctum::actingAs($other, ['*'], 'landlord');

    $this->getJson("/api/notifications/{$notificationId}")
        ->assertNotFound();
});

test('password changed listener sends templated notification', function (): void {
    Notification::fake();

    $user = notifLandlord();

    app(SendPasswordChangedNotification::class)->handle(
        new PasswordChanged($user, 'changed'),
    );

    Notification::assertSentTo($user, TemplatedNotification::class);
});

test('template renderer rejects unknown placeholders', function (): void {
    $renderer = app(TemplateRenderer::class);

    expect(fn () => $renderer->assertNoUnknownPlaceholders('Hi {{bad}}', ['user_name']))
        ->toThrow(InvalidArgumentException::class);
});

test('payment succeeded listener notifies landlord admins with subscriptions.view', function (): void {
    Notification::fake();

    $plan = Plan::query()->create([
        'name' => 'Starter',
        'slug' => 'starter-notif-'.uniqid(),
        'price' => '0.00',
        'currency' => 'NGN',
        'billing_interval' => 'monthly',
        'billing_interval_count' => 1,
        'trial_days' => 0,
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 1,
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Store',
        'slug' => 'store-notif-'.uniqid(),
        'email' => 'store@example.com',
        'status' => 'active',
        'is_active' => true,
    ]));

    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'status' => 'active',
        'auto_renew' => true,
        'cancel_at_period_end' => false,
    ]);

    $transaction = PaymentTransaction::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'subscription_id' => $subscription->id,
        'provider' => 'paystack',
        'reference' => 'ref_'.uniqid(),
        'amount' => '1000.00',
        'currency' => 'NGN',
        'status' => 'successful',
    ]);

    $admin = notifLandlord(['admin']);

    app(SendPaymentSucceededNotification::class)->handle(
        new PaymentSucceeded($transaction),
    );

    Notification::assertSentTo($admin, TemplatedNotification::class);
});

test('notification preferences endpoint updates channel toggles', function (): void {
    $user = notifLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->getJson('/api/notification-preferences')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->putJson('/api/notification-preferences', [
        'preferences' => [
            [
                'notification_key' => 'user.created',
                'channel' => 'push',
                'enabled' => false,
            ],
        ],
    ])->assertOk();

    expect(
        NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('notification_key', 'user.created')
            ->where('channel', NotificationChannel::Push)
            ->value('enabled')
    )->toBeFalse();
});
