<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Contracts\Payment\PaymentWebhookHandlerInterface;
use Illuminate\Http\Request;

/**
 * Moniepoint webhook scaffold — intentionally non-operational.
 *
 * Decision required: official Moniepoint webhook docs + signing scheme before enabling.
 */
class MoniepointPaymentWebhookHandler implements PaymentWebhookHandlerInterface
{
    public function provider(): string
    {
        return 'moniepoint';
    }

    public function verifySignature(Request $request): bool
    {
        return false;
    }

    public function eventId(Request $request): ?string
    {
        return null;
    }

    public function isSuccessfulCharge(Request $request): bool
    {
        return false;
    }

    public function paymentReference(Request $request): ?string
    {
        return null;
    }
}
