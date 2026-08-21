<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Subscription;

use App\DTO\Payment\PaymentInitiationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Landlord\Subscription\ChangePlanRequest;
use App\Http\Requests\Landlord\Subscription\SubscribeRequest;
use App\Http\Requests\Landlord\Subscription\VerifyPaymentRequest;
use App\Http\Resources\Landlord\Subscription\SubscriptionResource;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Subscription\SubscriptionService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Landlord subscription management for a tenant.
 */
class SubscriptionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  SubscriptionService  $subscriptionService
     */
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    /**
     * Show the tenant's current access-granting subscription.
     *
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Current tenant subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource|null, meta: null, errors: null}',
    )]
    public function current(Tenant $tenant): JsonResponse
    {
        $subscription = $this->subscriptionService->currentForTenant($tenant);

        return $this->success(
            $subscription !== null ? new SubscriptionResource($subscription) : null,
            'Current subscription retrieved successfully.',
        );
    }

    /**
     * Subscribe a tenant to a plan.
     *
     * @param  SubscribeRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Subscription created (and payment initiated when paid).',
        type: 'array{success: true, message: string, data: array{subscription: SubscriptionResource, payment: array{reference: string, authorization_url: string, access_code: string|null, provider: string}|null}, meta: null, errors: null}',
    )]
    public function subscribe(SubscribeRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $result = $this->subscriptionService->subscribe($tenant, $plan, $request->safe()->except(['plan_id']));

        return $this->created(
            $this->subscriptionPayload($result),
            'Subscription created successfully.',
        );
    }

    /**
     * Verify a pending payment and activate the subscription.
     *
     * @param  VerifyPaymentRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Verified and activated subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}',
    )]
    public function verify(VerifyPaymentRequest $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->subscriptionService->verifyPayment(
            $tenant,
            $request->validated('reference'),
        );

        return $this->success(
            new SubscriptionResource($subscription),
            'Payment verified and subscription activated.',
        );
    }

    /**
     * Cancel a tenant subscription.
     *
     * @param  CancelSubscriptionRequest  $request
     * @param  Tenant  $tenant
     * @param  Subscription  $subscription
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Cancelled subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}',
    )]
    public function cancel(CancelSubscriptionRequest $request, Tenant $tenant, Subscription $subscription): JsonResponse
    {
        $this->assertOwns($tenant, $subscription);

        $subscription = $this->subscriptionService->cancel(
            $subscription,
            (bool) $request->validated('immediately', false),
        );

        return $this->updated(
            new SubscriptionResource($subscription),
            'Subscription cancelled successfully.',
        );
    }

    /**
     * Change the tenant's plan.
     *
     * @param  ChangePlanRequest  $request
     * @param  Tenant  $tenant
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Plan change result.',
        type: 'array{success: true, message: string, data: array{subscription: SubscriptionResource, payment: array{reference: string, authorization_url: string, access_code: string|null, provider: string}|null}, meta: null, errors: null}',
    )]
    public function changePlan(ChangePlanRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $result = $this->subscriptionService->changePlan(
            $tenant,
            $plan,
            $request->safe()->except(['plan_id']),
        );

        return $this->updated(
            $this->subscriptionPayload($result),
            'Plan change initiated successfully.',
        );
    }

    /**
     * Subscription payload.
     *
     * @param  array{subscription: Subscription, payment: PaymentInitiationResult|null}  $result
     * @return array{subscription: SubscriptionResource, payment: array<string, mixed>|null}
     */
    protected function subscriptionPayload(array $result): array
    {
        return [
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => $result['payment']?->toArray(),
        ];
    }

    /**
     * Assert owns.
     *
     * @param  Tenant  $tenant
     * @param  Subscription  $subscription
     * @return void
     *
     * @throws NotFoundHttpException
     */
    protected function assertOwns(Tenant $tenant, Subscription $subscription): void
    {
        if ((string) $subscription->tenant_id !== (string) $tenant->getTenantKey()) {
            throw new NotFoundHttpException('Subscription not found for this tenant.');
        }
    }
}
