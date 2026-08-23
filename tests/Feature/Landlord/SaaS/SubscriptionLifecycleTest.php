<?php

declare(strict_types=1);

use App\Enums\Landlord\BillingInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Enums\Landlord\TenantStatus;
use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiring;
use App\Jobs\ProcessSubscriptionLifecycleJob;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Services\Landlord\Subscription\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.queue' => false,
        'notifications.subscription.expiring_days' => 7,
        'tenancy.database.prefix' => 'testing_tenant_',
        'tenancy.database.suffix' => '',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function lifecyclePlan(array $overrides = []): Plan
{
    return Plan::query()->create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-life-'.uniqid(),
        'price' => '0.00',
        'currency' => 'NGN',
        'billing_interval' => BillingInterval::Monthly,
        'billing_interval_count' => 1,
        'trial_days' => 0,
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 1,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function lifecycleTenant(array $overrides = []): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function () use ($overrides): Tenant {
        return Tenant::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Lifecycle Store',
            'slug' => 'life-'.uniqid(),
            'email' => 'life@example.com',
            'status' => TenantStatus::Active,
            'is_active' => true,
        ], $overrides));
    });

    Domain::query()->create([
        'domain' => $tenant->slug.'.test',
        'tenant_id' => $tenant->getTenantKey(),
        'is_primary' => true,
    ]);

    TenantProfile::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'display_name' => $tenant->name,
        'slug' => $tenant->slug,
        'is_public' => true,
        'email' => $tenant->email,
    ]);

    return $tenant->fresh(['domains', 'profile']) ?? $tenant;
}

test('notifies once for subscriptions ending within the expiring window', function (): void {
    Event::fake([SubscriptionExpiring::class, SubscriptionExpired::class]);

    $plan = lifecyclePlan();
    $tenant = lifecycleTenant();
    $endsAt = now()->addDays(3);

    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'auto_renew' => true,
        'cancel_at_period_end' => false,
        'current_period_start' => now()->subDays(27),
        'current_period_end' => $endsAt,
    ]);

    $counts = app(SubscriptionLifecycleService::class)->process();

    expect($counts['expiring_notified'])->toBe(1)
        ->and($counts['expired'])->toBe(0);

    Event::assertDispatched(SubscriptionExpiring::class, fn (SubscriptionExpiring $event): bool => $event->subscription->is($subscription));
    Event::assertNotDispatched(SubscriptionExpired::class);

    $subscription->refresh();
    expect($subscription->metadata['expiring_notified_period_end'] ?? null)->toBe($endsAt->toDateString());

    $countsAgain = app(SubscriptionLifecycleService::class)->process();

    expect($countsAgain['expiring_notified'])->toBe(0);
    Event::assertDispatchedTimes(SubscriptionExpiring::class, 1);
});

test('expires overdue subscriptions and dispatches subscription.expired', function (): void {
    Event::fake([SubscriptionExpiring::class, SubscriptionExpired::class]);

    $plan = lifecyclePlan();
    $tenant = lifecycleTenant();

    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'auto_renew' => true,
        'cancel_at_period_end' => false,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
    ]);

    $counts = app(SubscriptionLifecycleService::class)->process();

    expect($counts['expired'])->toBe(1)
        ->and($counts['expiring_notified'])->toBe(0);

    Event::assertDispatched(SubscriptionExpired::class);
    Event::assertNotDispatched(SubscriptionExpiring::class);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->auto_renew)->toBeFalse()
        ->and($subscription->ends_at)->not->toBeNull();
});

test('expires trialing subscriptions when trial_ends_at has passed', function (): void {
    Event::fake([SubscriptionExpired::class]);

    $plan = lifecyclePlan(['trial_days' => 14]);
    $tenant = lifecycleTenant();

    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Trialing,
        'auto_renew' => false,
        'cancel_at_period_end' => false,
        'trial_ends_at' => now()->subHour(),
        'current_period_start' => now()->subDays(14),
        'current_period_end' => now()->addMonth(),
    ]);

    app(SubscriptionLifecycleService::class)->expireDue();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Expired);
    Event::assertDispatched(SubscriptionExpired::class);
});

test('process subscription lifecycle job runs the service', function (): void {
    Event::fake([SubscriptionExpired::class]);

    $plan = lifecyclePlan();
    $tenant = lifecycleTenant();

    Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::PastDue,
        'auto_renew' => false,
        'cancel_at_period_end' => false,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->subMinutes(5),
    ]);

    (new ProcessSubscriptionLifecycleJob)->handle(app(SubscriptionLifecycleService::class));

    Event::assertDispatched(SubscriptionExpired::class);
});
