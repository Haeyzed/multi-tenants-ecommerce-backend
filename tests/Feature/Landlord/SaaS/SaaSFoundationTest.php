<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentVerificationResult;
use App\Enums\Landlord\BillingInterval;
use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentTransactionStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Enums\Landlord\TenantStatus;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureFeature;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Feature;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Models\Landlord\User;
use App\Models\Landlord\WebhookEvent;
use App\Services\Landlord\Domain\DomainService;
use App\Services\Landlord\Feature\FeatureAccessService;
use App\Services\Landlord\Subscription\SubscriptionService;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Payment\PaymentManager;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    config([
        'payment.default' => 'paystack',
        'payment.drivers.paystack.secret_key' => 'sk_test_dummy',
        'payment.drivers.paystack.public_key' => 'pk_test_dummy',
        'payment.drivers.paystack.webhook_secret' => 'sk_test_dummy',
        'payment.drivers.paystack.currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
        'tenancy.database.prefix' => 'testing_tenant_',
        'tenancy.database.suffix' => '',
        'tenancy.bootstrappers' => array_values(array_filter(
            config('tenancy.bootstrappers'),
            static fn (string $bootstrapper): bool => ! str_contains($bootstrapper, 'CacheTenancyBootstrapper'),
        )),
    ]);
});

/**
 * @param  list<string>  $roles
 */
function saasLandlord(array $roles = ['admin']): User
{
    $user = User::factory()->create();
    $user->syncRoles($roles);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makePlan(array $overrides = []): Plan
{
    return Plan::query()->create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-'.uniqid(),
        'description' => 'Test plan',
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
function makeFeature(array $overrides = []): Feature
{
    $slug = $overrides['slug'] ?? 'products-'.uniqid();

    return Feature::query()->create(array_merge([
        'name' => 'Products',
        'slug' => $slug,
        'description' => 'Products feature',
        'is_active' => true,
    ], $overrides));
}

/**
 * Create a tenant without Stancl provisioning (for subscription/feature tests).
 *
 * @param  array<string, mixed>  $overrides
 */
function makeTenantRecord(array $overrides = []): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function () use ($overrides): Tenant {
        return Tenant::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Store',
            'slug' => 'acme-'.uniqid(),
            'email' => 'owner@acme.test',
            'phone' => null,
            'status' => TenantStatus::Active,
            'is_active' => true,
        ], $overrides));
    });

    Domain::query()->create([
        'domain' => ($tenant->slug).'.test',
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

    return $tenant->fresh(['domains', 'profile']);
}

test('landlord can create retrieve update and delete a tenant with provisioning', function (): void {
    $user = saasLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $create = $this->postJson('/api/tenants', [
        'name' => 'Provisioned Store',
        'domain' => 'provisioned-store.test',
        'admin' => [
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => 'ada@provisioned.test',
            'password' => 'Password1!',
        ],
        'profile' => [
            'display_name' => 'Provisioned Store',
            'is_public' => true,
        ],
    ]);

    $create->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Provisioned Store');

    $tenantId = $create->json('data.id');

    $this->getJson("/api/tenants/{$tenantId}")
        ->assertOk()
        ->assertJsonPath('data.id', $tenantId)
        ->assertJsonPath('data.domains.0.domain', 'provisioned-store.test');

    $this->putJson("/api/tenants/{$tenantId}", [
        'name' => 'Renamed Store',
        'status' => 'active',
    ])->assertOk()->assertJsonPath('data.name', 'Renamed Store');

    $this->deleteJson("/api/tenants/{$tenantId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Tenant::query()->whereKey($tenantId)->exists())->toBeFalse();
});

test('domains are unique and scoped to owning tenant', function (): void {
    $user = saasLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $tenantA = makeTenantRecord(['name' => 'Tenant A', 'slug' => 'tenant-a-'.uniqid()]);
    $tenantB = makeTenantRecord(['name' => 'Tenant B', 'slug' => 'tenant-b-'.uniqid()]);

    $this->postJson("/api/tenants/{$tenantA->id}/domains", [
        'domain' => 'shared-domain.test',
    ])->assertCreated();

    $this->postJson("/api/tenants/{$tenantB->id}/domains", [
        'domain' => 'shared-domain.test',
    ])->assertUnprocessable();

    $domainId = Domain::query()->where('domain', 'shared-domain.test')->value('id');

    $this->getJson("/api/tenants/{$tenantB->id}/domains/{$domainId}")
        ->assertNotFound();
});

test('public store profile is resolved by slug and private profiles are hidden', function (): void {
    $tenant = makeTenantRecord(['slug' => 'public-shop-'.uniqid()]);
    $tenant->profile->update(['is_public' => true, 'slug' => $tenant->slug]);

    $this->getJson('/api/public/stores/'.$tenant->slug)
        ->assertOk()
        ->assertJsonPath('data.slug', $tenant->slug)
        ->assertJsonMissingPath('data.tenant_id');

    $private = makeTenantRecord(['slug' => 'private-shop-'.uniqid()]);
    $private->profile->update(['is_public' => false, 'slug' => $private->slug]);

    $this->getJson('/api/public/stores/'.$private->slug)
        ->assertNotFound();
});

test('landlord can manage plans features and public plans list', function (): void {
    $user = saasLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $feature = $this->postJson('/api/features', [
        'name' => 'Advanced Reports',
        'slug' => 'advanced-reports',
        'description' => 'Reports',
        'is_active' => true,
    ])->assertCreated()->json('data');

    $plan = $this->postJson('/api/plans', [
        'name' => 'Professional',
        'slug' => 'professional',
        'price' => '15000.00',
        'currency' => 'NGN',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'is_public' => true,
    ])->assertCreated()->json('data');

    $this->putJson("/api/plans/{$plan['id']}/features", [
        'features' => [
            ['feature' => 'advanced-reports', 'enabled' => true, 'limit' => 10],
        ],
    ])->assertOk()
        ->assertJsonPath('data.features.0.slug', 'advanced-reports');

    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->getJson('/api/public/plans')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['slug' => 'professional']);

    expect($feature['slug'])->toBe('advanced-reports');
});

test('landlord options endpoints return label and value pairs', function (): void {
    $user = saasLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $tenant = makeTenantRecord(['name' => 'Options Tenant', 'slug' => 'options-tenant-'.uniqid()]);
    $plan = makePlan(['name' => 'Options Plan', 'slug' => 'options-plan-'.uniqid()]);
    $feature = makeFeature(['name' => 'Options Feature', 'slug' => 'options-feature']);

    $this->getJson('/api/tenants/options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment([
            'label' => 'Options Tenant',
            'value' => (string) $tenant->getTenantKey(),
        ]);

    $this->getJson('/api/plans/options')
        ->assertOk()
        ->assertJsonFragment([
            'label' => 'Options Plan',
            'value' => $plan->id,
        ]);

    $this->getJson('/api/features/options')
        ->assertOk()
        ->assertJsonFragment([
            'label' => 'Options Feature',
            'value' => 'options-feature',
        ]);
});

test('tenant can subscribe to a free plan immediately', function (): void {
    $tenant = makeTenantRecord();
    $plan = makePlan(['name' => 'Free', 'slug' => 'free-'.uniqid(), 'price' => '0.00']);
    $feature = makeFeature(['slug' => 'products']);
    $plan->features()->sync([
        $feature->id => ['is_enabled' => true, 'limit' => 100],
    ]);

    $result = app(SubscriptionService::class)->subscribe($tenant, $plan);

    expect($result['payment'])->toBeNull()
        ->and($result['subscription']->status)->toBe(SubscriptionStatus::Active)
        ->and($result['subscription']->plan_id)->toBe($plan->id);

    $access = app(FeatureAccessService::class);
    expect($access->has($tenant->fresh(), 'products'))->toBeTrue()
        ->and($access->limit($tenant->fresh(), 'products'))->toBe(100)
        ->and($access->canUse($tenant->fresh(), 'products', 99))->toBeTrue()
        ->and($access->canUse($tenant->fresh(), 'products', 100))->toBeFalse();
});

test('paid subscription initializes payment and activates after verification', function (): void {
    $tenant = makeTenantRecord();
    $plan = makePlan([
        'slug' => 'paid-flow-'.uniqid(),
        'price' => '15000.00',
        'currency' => 'NGN',
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->with('NGN')->andReturn(true);
    $gateway->shouldReceive('initializePayment')->once()->andReturnUsing(function ($request) {
        return new PaymentInitiationResult(
            reference: $request->reference,
            authorizationUrl: 'https://paystack.test/authorize',
            accessCode: 'code',
            provider: 'paystack',
        );
    });
    $gateway->shouldReceive('verifyPayment')->once()->andReturnUsing(function (string $reference) {
        return new PaymentVerificationResult(
            successful: true,
            reference: $reference,
            providerTransactionId: 'txn_99',
            amount: '15000.00',
            currency: 'NGN',
            paidAt: now(),
        );
    });

    $manager = Mockery::mock(PaymentManager::class);
    $manager->shouldReceive('driver')->andReturn($gateway);
    $this->app->instance(PaymentManager::class, $manager);

    $service = app(SubscriptionService::class);
    $init = $service->subscribe($tenant, $plan, ['email' => $tenant->email]);

    expect($init['payment'])->not->toBeNull()
        ->and($init['subscription']->status)->toBe(SubscriptionStatus::Pending);

    $reference = $init['payment']->reference;
    $activated = $service->verifyPayment($tenant, $reference);

    expect($activated->status)->toBe(SubscriptionStatus::Active)
        ->and(PaymentTransaction::query()->where('reference', $reference)->value('status'))
        ->toBe(PaymentTransactionStatus::Successful);
});

test('invalid currency is rejected before payment initialization', function (): void {
    $tenant = makeTenantRecord();
    $plan = makePlan([
        'slug' => 'euro-plan-'.uniqid(),
        'price' => '10.00',
        'currency' => 'EUR',
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->with('EUR')->andReturn(false);

    $manager = Mockery::mock(PaymentManager::class);
    $manager->shouldReceive('driver')->andReturn($gateway);
    $this->app->instance(PaymentManager::class, $manager);

    app(SubscriptionService::class)->subscribe($tenant, $plan, ['email' => $tenant->email]);
})->throws(ValidationException::class);

test('subscription cancel and feature middleware deny access', function (): void {
    $tenant = makeTenantRecord();
    $plan = makePlan(['slug' => 'cancel-plan-'.uniqid(), 'price' => '0.00']);
    $feature = makeFeature(['slug' => 'advanced-reports']);
    $plan->features()->sync([$feature->id => ['is_enabled' => true, 'limit' => null]]);

    $subscription = app(SubscriptionService::class)->subscribe($tenant, $plan)['subscription'];
    app(SubscriptionService::class)->cancel($subscription, true);

    expect(app(FeatureAccessService::class)->has($tenant->fresh(), 'advanced-reports'))->toBeFalse();

    tenancy()->tenant = $tenant->fresh();
    tenancy()->initialized = true;

    $featureResponse = app(EnsureFeature::class)->handle(
        Request::create('/api/reports', 'GET'),
        fn () => response('ok'),
        'advanced-reports',
    );

    expect($featureResponse->getStatusCode())->toBe(403);

    $subscriptionResponse = app(EnsureActiveSubscription::class)->handle(
        Request::create('/api/dashboard', 'GET'),
        fn () => response('ok'),
    );

    expect($subscriptionResponse->getStatusCode())->toBe(402);

    tenancy()->tenant = null;
    tenancy()->initialized = false;
});

test('paystack webhook is idempotent and signature protected', function (): void {
    Http::fake([
        'https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'id' => 555,
                'status' => 'success',
                'reference' => 'wh_ref_1',
                'amount' => 500000,
                'currency' => 'NGN',
                'paid_at' => now()->toIso8601String(),
            ],
        ], 200),
    ]);

    $tenant = makeTenantRecord();
    $plan = makePlan(['slug' => 'wh-plan-'.uniqid(), 'price' => '5000.00']);

    $subscription = Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'plan_id' => $plan->id,
        'provider' => PaymentProvider::Paystack,
        'status' => SubscriptionStatus::Pending,
    ]);

    $transaction = PaymentTransaction::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'subscription_id' => $subscription->id,
        'provider' => PaymentProvider::Paystack,
        'reference' => 'wh_ref_1',
        'amount' => '5000.00',
        'currency' => 'NGN',
        'status' => PaymentTransactionStatus::Pending,
    ]);

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'id' => 555,
            'reference' => 'wh_ref_1',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => 'success',
            'paid_at' => now()->toIso8601String(),
        ],
    ], JSON_THROW_ON_ERROR);

    $secret = config('payment.drivers.paystack.webhook_secret');
    $signature = hash_hmac('sha512', $payload, $secret);

    $this->call(
        'POST',
        '/api/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $signature,
        ],
        $payload,
    )->assertOk()->assertJsonPath('data.processed', true);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($transaction->fresh()->status)->toBe(PaymentTransactionStatus::Successful);

    $this->call(
        'POST',
        '/api/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $signature,
        ],
        $payload,
    )->assertOk()->assertJsonPath('data.duplicate', true);

    expect(WebhookEvent::query()->count())->toBe(1);

    $this->call(
        'POST',
        '/api/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => 'bad',
        ],
        $payload,
    )->assertForbidden();
});

test('tenant a cannot access tenant b subscription endpoints', function (): void {
    $user = saasLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $tenantA = makeTenantRecord(['slug' => 'iso-a-'.uniqid()]);
    $tenantB = makeTenantRecord(['slug' => 'iso-b-'.uniqid()]);
    $plan = makePlan(['slug' => 'iso-plan-'.uniqid(), 'price' => '0.00']);

    $subscriptionB = app(SubscriptionService::class)->subscribe($tenantB, $plan)['subscription'];

    $this->postJson("/api/tenants/{$tenantA->id}/subscription/{$subscriptionB->id}/cancel", [
        'immediately' => true,
    ])->assertNotFound();
});

test('paystack gateway converts amounts and verifies via http fake', function (): void {
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'access',
                'reference' => 'ref_http_1',
            ],
        ]),
        'https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'id' => 99,
                'status' => 'success',
                'reference' => 'ref_http_1',
                'amount' => 1500000,
                'currency' => 'NGN',
                'paid_at' => now()->toIso8601String(),
            ],
        ]),
    ]);

    $gateway = app(PaystackGateway::class);

    $init = $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '15000.00',
        currency: 'NGN',
        email: 'buyer@example.com',
        reference: 'ref_http_1',
    ));

    expect($init->authorizationUrl)->toContain('paystack');

    $verify = $gateway->verifyPayment('ref_http_1');
    expect($verify->successful)->toBeTrue()
        ->and($verify->amount)->toBe('15000.00');
});

test('additional domains require custom-domain feature when subscribed', function (): void {
    $tenant = makeTenantRecord();
    $plan = makePlan(['name' => 'Starter Domains', 'slug' => 'starter-domains-'.uniqid(), 'price' => '0.00']);
    $feature = makeFeature(['slug' => 'custom-domain', 'name' => 'Custom Domain']);
    $plan->features()->sync([
        $feature->id => ['is_enabled' => false, 'limit' => null],
    ]);

    app(SubscriptionService::class)->subscribe($tenant, $plan);

    expect(fn () => app(DomainService::class)->store($tenant->fresh(), [
        'domain' => 'custom-'.$tenant->slug.'.test',
    ]))->toThrow(ValidationException::class);

    $plan->features()->sync([
        $feature->id => ['is_enabled' => true, 'limit' => null],
    ]);

    $domain = app(DomainService::class)->store($tenant->fresh(), [
        'domain' => 'custom-'.$tenant->slug.'.test',
    ]);

    expect($domain->domain)->toBe('custom-'.$tenant->slug.'.test')
        ->and($tenant->fresh()->domains()->count())->toBe(2);
});
