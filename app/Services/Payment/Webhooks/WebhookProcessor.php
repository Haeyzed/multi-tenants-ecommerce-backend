<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Enums\Landlord\PaymentProvider;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Dispatches provider webhook payloads to the correct handler.
 */
class WebhookProcessor
{
    /**
     * Create a new webhook processor.
     */
    public function __construct(private readonly PaystackWebhookHandler $paystackWebhookHandler) {}

    /**
     * Process an inbound payment provider webhook.
     *
     * @return array{processed: bool, duplicate?: bool, event_type?: string|null}
     */
    public function process(string $provider, Request $request): array
    {
        $provider = strtolower($provider);

        return match ($provider) {
            PaymentProvider::Paystack->value => $this->paystackWebhookHandler->handle($request),
            default => throw new InvalidArgumentException("Unsupported webhook provider [{$provider}]."),
        };
    }
}
