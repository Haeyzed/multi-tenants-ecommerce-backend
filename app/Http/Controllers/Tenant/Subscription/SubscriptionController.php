<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Tenant\Subscription\ChangePlanRequest;
use App\Http\Requests\Tenant\Subscription\SubscribeRequest;
use App\Http\Requests\Tenant\Subscription\VerifyPaymentRequest;
use App\Http\Resources\Landlord\Subscription\SubscriptionResource;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Subscription\SubscriptionService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Tenant-facing subscription endpoints for the current tenant.
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
     * Show the current tenant subscription.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Current tenant subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource|null, meta: null, errors: null}',
    )]
    public function current(): JsonResponse
    {
        $subscription = $this->subscriptionService->currentForTenant($this->currentTenant());

        return $this->success(
            $subscription !== null ? new SubscriptionResource($subscription) : null,
            'Current subscription retrieved successfully.',
        );
    }

    /**
     * Subscribe the current tenant to a plan.
     *
     * @param  SubscribeRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Subscription created (and payment initiated when paid).',
        type: 'array{success: true, message: string, data: array{subscription: SubscriptionResource, payment: array{reference: string, authorization_url: string, access_code: string|null, provider: string}|null}, meta: null, errors: null}',
    )]
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $result = $this->subscriptionService->subscribe(
            $this->currentTenant(),
            $plan,
            $request->safe()->except(['plan_id']),
        );

        return $this->created([
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => $result['payment']?->toArray(),
        ], 'Subscription created successfully.');
    }

    /**
     * Verify a pending payment for the current tenant.
     *
     * @param  VerifyPaymentRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Verified and activated subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}',
    )]
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $subscription = $this->subscriptionService->verifyPayment(
            $this->currentTenant(),
            $request->validated('reference'),
        );

        return $this->success(
            new SubscriptionResource($subscription),
            'Payment verified and subscription activated.',
        );
    }

    /**
     * Cancel the current tenant subscription.
     *
     * @param  CancelSubscriptionRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Cancelled subscription.',
        type: 'array{success: true, message: string, data: SubscriptionResource, meta: null, errors: null}',
    )]
    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $tenant = $this->currentTenant();
        $subscription = $this->subscriptionService->currentForTenant($tenant);

        if ($subscription === null) {
            return $this->notFound('No active subscription found.');
        }

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
     * Change the current tenant's plan.
     *
     * @param  ChangePlanRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Plan change result.',
        type: 'array{success: true, message: string, data: array{subscription: SubscriptionResource, payment: array{reference: string, authorization_url: string, access_code: string|null, provider: string}|null}, meta: null, errors: null}',
    )]
    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $result = $this->subscriptionService->changePlan(
            $this->currentTenant(),
            $plan,
            $request->safe()->except(['plan_id']),
        );

        return $this->updated([
            'subscription' => new SubscriptionResource($result['subscription']),
            'payment' => $result['payment']?->toArray(),
        ], 'Plan change initiated successfully.');
    }

    /**
     * Resolve the current tenant from tenancy context.
     *
     * @return Tenant
     */
    protected function currentTenant(): Tenant
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('Tenant context is required.');
        }

        return $tenant;
    }
}
