<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderPayment
 */
class OrderPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderPayment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'customer_id' => $payment->customer_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            'reference' => $payment->reference,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
            'failed_at' => $payment->failed_at,
            'metadata' => $payment->metadata,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ];
    }
}
