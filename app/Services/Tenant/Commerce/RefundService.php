<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\DTO\Payment\PaymentRefundResult;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Events\RefundCompleted;
use App\Events\RefundInitiated;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Refund;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Process full and partial payment refunds.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly AccountingService $accounting,
    ) {}

    /**
     * @param  array{order_id?: int|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Refund>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Refund::query()->with(['order', 'payment'])->latest('id');

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * @param  array{amount?: string|null, reason?: string|null}  $data
     */
    public function create(Order $order, OrderPayment $payment, array $data = []): Refund
    {
        if ($payment->order_id !== $order->id) {
            throw ValidationException::withMessages([
                'payment' => 'Payment does not belong to this order.',
            ]);
        }

        if ($payment->status !== OrderPaymentRecordStatus::Successful) {
            throw ValidationException::withMessages([
                'payment' => 'Only successful payments can be refunded.',
            ]);
        }

        $requestedAmount = isset($data['amount']) ? (string) $data['amount'] : (string) $payment->amount;
        $refundable = $this->refundableAmount($payment);

        if (bccomp($requestedAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        if (bccomp($requestedAmount, $refundable, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount exceeds the refundable balance.',
            ]);
        }

        $reference = 'REF-'.$order->order_number.'-'.uniqid();
        $isFullRefund = bccomp($requestedAmount, $refundable, 2) === 0;

        return DB::transaction(function () use ($order, $payment, $data, $requestedAmount, $reference, $isFullRefund): Refund {
            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'order_payment_id' => $payment->id,
                'amount' => $requestedAmount,
                'currency' => $payment->currency,
                'reference' => $reference,
                'status' => RefundStatus::Processing,
                'reason' => $data['reason'] ?? null,
            ]);

            event(new RefundInitiated($refund));

            $gateway = $this->paymentManager->driver($payment->gateway);
            $providerTransactionId = $payment->provider_transaction_id;

            if ($providerTransactionId === null || $providerTransactionId === '') {
                throw ValidationException::withMessages([
                    'payment' => 'Payment has no provider transaction id for refund.',
                ]);
            }

            $result = $gateway instanceof PaystackGateway
                ? $gateway->refundPaymentDetailed($providerTransactionId, $requestedAmount)
                : new PaymentRefundResult(
                    successful: $gateway->refundPayment($providerTransactionId, $requestedAmount),
                );

            if (! $result->successful) {
                $refund->status = RefundStatus::Failed;
                $refund->metadata = ['gateway' => $result->raw, 'message' => $result->message];
                $refund->save();

                throw ValidationException::withMessages([
                    'refund' => $result->message ?? 'Gateway refund failed.',
                ]);
            }

            $refund->status = RefundStatus::Completed;
            $refund->provider_refund_id = $result->providerRefundId;
            $refund->processed_at = now();
            $refund->metadata = ['gateway' => $result->raw];
            $refund->save();

            $remaining = $this->refundableAmount($payment->fresh() ?? $payment);

            if (bccomp($remaining, '0', 2) === 0) {
                $order->payment_status = OrderPaymentStatus::Refunded;
            } else {
                $order->payment_status = OrderPaymentStatus::PartiallyRefunded;
            }
            $order->save();

            if ($isFullRefund) {
                $this->accounting->postRefund($order);
            } else {
                $this->accounting->postPartialRefund($order, $requestedAmount);
            }

            $refund = $refund->fresh(['order', 'payment']) ?? $refund;
            event(new RefundCompleted($refund));

            return $refund;
        });
    }

    public function show(Refund $refund): Refund
    {
        return $refund->load(['order', 'payment']);
    }

    protected function refundableAmount(OrderPayment $payment): string
    {
        $refunded = Refund::query()
            ->where('order_payment_id', $payment->id)
            ->where('status', RefundStatus::Completed)
            ->sum('amount');

        return Money::sub((string) $payment->amount, (string) $refunded);
    }
}
