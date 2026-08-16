<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Models\Tenant\OrderPayment;
use App\Services\Payment\Webhooks\FakePaymentWebhookHandler;
use App\Services\Payment\Webhooks\FlutterwavePaymentWebhookHandler;
use App\Services\Payment\Webhooks\MoniepointPaymentWebhookHandler;
use App\Services\Payment\Webhooks\MonnifyPaymentWebhookHandler;
use App\Services\Payment\Webhooks\PaystackPaymentWebhookHandler;
use App\Services\Tenant\Commerce\OrderPaymentService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves provider webhook handlers and forwards verified events to order payments.
 */
class PaymentWebhookManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * Handle an inbound provider webhook.
     *
     * @return array{processed: bool, duplicate?: bool, payment?: OrderPayment|null}
     */
    public function handle(string $provider, Request $request): array
    {
        $handler = $this->handler($provider);

        if (! $handler->verifySignature($request)) {
            throw new AccessDeniedHttpException("Invalid {$provider} webhook signature.");
        }

        if (! $handler->isSuccessfulCharge($request)) {
            return ['processed' => false];
        }

        $reference = $handler->paymentReference($request);

        if ($reference === null || $reference === '') {
            return ['processed' => false];
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return $this->container->make(OrderPaymentService::class)->handleVerifiedWebhook(
            provider: $handler->provider(),
            reference: $reference,
            payload: $payload,
            eventId: $handler->eventId($request),
            eventType: $this->resolveEventType($request, $handler),
            rawBody: $request->getContent() !== '' ? $request->getContent() : null,
        );
    }

    public function handler(string $provider): PaymentWebhookHandlerInterface
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'paystack' => $this->container->make(PaystackPaymentWebhookHandler::class),
            'flutterwave' => $this->container->make(FlutterwavePaymentWebhookHandler::class),
            'monnify' => $this->container->make(MonnifyPaymentWebhookHandler::class),
            'moniepoint' => $this->container->make(MoniepointPaymentWebhookHandler::class),
            'fake' => $this->container->make(FakePaymentWebhookHandler::class),
            default => throw new InvalidArgumentException("Unsupported payment webhook provider [{$provider}]."),
        };
    }

    protected function resolveEventType(Request $request, PaymentWebhookHandlerInterface $handler): ?string
    {
        $candidates = [
            $request->input('event'),
            $request->input('eventType'),
            $request->input('type'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return $handler->isSuccessfulCharge($request) ? 'successful_charge' : null;
    }
}
