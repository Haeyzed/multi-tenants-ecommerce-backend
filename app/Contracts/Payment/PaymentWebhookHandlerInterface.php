<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use Illuminate\Http\Request;

/**
 * Provider-specific webhook verification and payload extraction.
 */
interface PaymentWebhookHandlerInterface
{
    /**
     * Stable provider name matching PaymentManager driver names.
     */
    public function provider(): string;

    /**
     * Verify the inbound webhook signature / authenticity headers.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Stable provider event id for idempotency, when available.
     */
    public function eventId(Request $request): ?string;

    /**
     * Whether the payload represents a successful charge / collection.
     */
    public function isSuccessfulCharge(Request $request): bool;

    /**
     * Merchant payment reference used to look up OrderPayment.
     */
    public function paymentReference(Request $request): ?string;
}
