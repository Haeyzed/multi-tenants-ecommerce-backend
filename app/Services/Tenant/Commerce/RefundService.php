<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\DTO\Payment\PaymentRefundResult;
use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use App\Events\RefundCompleted;
use App\Events\RefundInitiated;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\GiftCardTransaction;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Refund;
use App\Models\Tenant\StoreCreditTransaction;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Process full and partial payment refunds.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly AccountingService $accounting,
        private readonly GiftCardService $giftCards,
        private readonly StoreCreditService $storeCredit,
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
     * @param  array{amount?: string|null, reason?: string|null, restore_prepaid?: bool|null}  $data
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

        if (bccomp($requestedAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        $providerTransactionId = $payment->provider_transaction_id;

        if ($providerTransactionId === null || $providerTransactionId === '') {
            throw ValidationException::withMessages([
                'payment' => 'Payment has no provider transaction id for refund.',
            ]);
        }

        $reference = 'REF-'.$order->order_number.'-'.uniqid();

        /** @var array{refund: Refund, is_full_refund: bool} $claim */
        $claim = DB::transaction(function () use ($order, $payment, $data, $requestedAmount, $reference): array {
            /** @var OrderPayment $lockedPayment */
            $lockedPayment = OrderPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $refundable = $this->refundableAmount($lockedPayment);

            if (bccomp($requestedAmount, $refundable, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the refundable balance.',
                ]);
            }

            $isFullRefund = bccomp($requestedAmount, $refundable, 2) === 0;

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'order_payment_id' => $lockedPayment->id,
                'amount' => $requestedAmount,
                'currency' => $lockedPayment->currency,
                'reference' => $reference,
                'status' => RefundStatus::Processing,
                'reason' => $data['reason'] ?? null,
            ]);

            event(new RefundInitiated($refund));

            return [
                'refund' => $refund,
                'is_full_refund' => $isFullRefund,
            ];
        });

        $refund = $claim['refund'];
        $isFullRefund = $claim['is_full_refund'];
        $restorePrepaid = (bool) ($data['restore_prepaid'] ?? true);

        try {
            $gateway = $this->paymentManager->driver($payment->gateway);

            $result = $gateway instanceof PaystackGateway
                ? $gateway->refundPaymentDetailed(
                    $providerTransactionId,
                    $requestedAmount,
                    (string) $payment->currency,
                )
                : new PaymentRefundResult(
                    successful: $gateway->refundPayment($providerTransactionId, $requestedAmount),
                );
        } catch (Throwable $exception) {
            $this->markRefundFailed($refund, [
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $result->successful) {
            $this->markRefundFailed($refund, [
                'gateway' => $result->raw,
                'message' => $result->message,
            ]);

            throw ValidationException::withMessages([
                'refund' => $result->message ?? 'Gateway refund failed.',
            ]);
        }

        return DB::transaction(function () use ($refund, $payment, $order, $requestedAmount, $isFullRefund, $restorePrepaid, $result): Refund {
            /** @var Refund $lockedRefund */
            $lockedRefund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === RefundStatus::Completed) {
                return $lockedRefund->load(['order', 'payment']);
            }

            /** @var OrderPayment $lockedPayment */
            $lockedPayment = OrderPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRefund->status = RefundStatus::Completed;
            $lockedRefund->provider_refund_id = $result->providerRefundId;
            $lockedRefund->processed_at = now();
            $lockedRefund->metadata = ['gateway' => $result->raw];
            $lockedRefund->save();

            if ($isFullRefund && $restorePrepaid) {
                $this->accounting->postRefund($lockedOrder);
                $this->restoreAlternativeFunding($lockedOrder);
            } else {
                $this->accounting->postPartialRefund($lockedOrder, $requestedAmount);
            }

            $this->syncOrderPaymentStatus($lockedOrder, $lockedPayment);

            $lockedRefund = $lockedRefund->fresh(['order', 'payment']) ?? $lockedRefund;
            event(new RefundCompleted($lockedRefund));

            return $lockedRefund;
        });
    }

    /**
     * Allocate a refund across remaining gateway balance first, then prepaid tenders.
     *
     * @param  array{reason?: string|null}  $data
     */
    public function refundAllocated(Order $order, string $amount, array $data = []): Refund
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        /** @var OrderPayment|null $payment */
        $payment = OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('status', OrderPaymentRecordStatus::Successful)
            ->latest('id')
            ->first();

        $gatewayRemaining = $payment !== null
            ? $this->refundableAmount($payment)
            : '0.00';
        $prepaidRemaining = $this->prepaidRemaining($order);
        $available = Money::add($gatewayRemaining, $prepaidRemaining);

        if (bccomp($amount, $available, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount exceeds the remaining refundable balance.',
            ]);
        }

        $gatewayShare = bccomp($amount, $gatewayRemaining, 2) > 0
            ? $gatewayRemaining
            : $amount;
        $prepaidShare = Money::sub($amount, $gatewayShare);

        $primary = null;

        if ($payment !== null && bccomp($gatewayShare, '0', 2) > 0) {
            $primary = $this->create($order, $payment, [
                'amount' => $gatewayShare,
                'reason' => $data['reason'] ?? null,
                'restore_prepaid' => false,
            ]);
        }

        if (bccomp($prepaidShare, '0', 2) > 0) {
            $primary = $this->createPrepaid($order->fresh() ?? $order, [
                'amount' => $prepaidShare,
                'reason' => $data['reason'] ?? null,
            ]);
        }

        if ($primary === null) {
            throw ValidationException::withMessages([
                'amount' => 'No refundable gateway or prepaid balance remains.',
            ]);
        }

        return $primary;
    }

    /**
     * Refund an order's prepaid gift card / store credit (optionally alongside a gateway payment).
     *
     * @param  array{amount?: string|null, reason?: string|null}  $data
     */
    public function createPrepaid(Order $order, array $data = []): Refund
    {
        return DB::transaction(function () use ($order, $data): Refund {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            OrderPayment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', OrderPaymentRecordStatus::Successful)
                ->lockForUpdate()
                ->get();

            $prepaidRemaining = $this->prepaidRemaining($lockedOrder);

            if (bccomp($prepaidRemaining, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'order' => bccomp($this->prepaidTotal($lockedOrder), '0', 2) > 0
                        ? 'Prepaid funding for this order has already been restored.'
                        : 'Order has no prepaid gift card or store credit balance to restore.',
                ]);
            }

            $requestedAmount = isset($data['amount']) ? (string) $data['amount'] : $prepaidRemaining;

            if (bccomp($requestedAmount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount must be greater than zero.',
                ]);
            }

            if (bccomp($requestedAmount, $prepaidRemaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the remaining prepaid balance on this order.',
                ]);
            }

            $isFullPrepaidRestore = bccomp(Money::sub($prepaidRemaining, $requestedAmount), '0', 2) === 0;
            $reference = 'REF-PREPAID-'.$lockedOrder->order_number.'-'.uniqid();

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'order_payment_id' => null,
                'amount' => $requestedAmount,
                'currency' => $lockedOrder->currency,
                'reference' => $reference,
                'status' => RefundStatus::Completed,
                'reason' => $data['reason'] ?? null,
                'processed_at' => now(),
                'metadata' => [
                    'type' => 'prepaid_restore',
                    'full' => $isFullPrepaidRestore,
                ],
            ]);

            event(new RefundInitiated($refund));

            $this->restoreAlternativeFunding($lockedOrder, $requestedAmount);

            if ($isFullPrepaidRestore && bccomp($this->gatewayRefundableTotal($lockedOrder), '0', 2) === 0) {
                $this->accounting->postRefund($lockedOrder);
            } else {
                $this->accounting->postPartialRefund($lockedOrder, $requestedAmount);
            }

            $this->syncOrderPaymentStatus($lockedOrder);

            $refund = $refund->fresh(['order', 'payment']) ?? $refund;
            event(new RefundCompleted($refund));

            return $refund;
        });
    }

    public function show(Refund $refund): Refund
    {
        return $refund->load(['order', 'payment']);
    }

    /**
     * Return prepaid gift card / store credit for an order, capped by remaining unrestored amounts.
     *
     * Gift card is restored first (same order as checkout draw-down), then store credit.
     * Prior restore ledger rows reduce what is still available so partial refunds can be
     * followed by later restores without stranding the remainder.
     *
     * @return array{gift_card: string, store_credit: string}
     */
    public function restoreAlternativeFunding(Order $order, ?string $maxAmount = null): array
    {
        $restored = ['gift_card' => '0.00', 'store_credit' => '0.00'];
        $remainingBudget = $maxAmount !== null ? Money::add($maxAmount, '0') : null;

        if (Schema::hasColumn('orders', 'gift_card_amount') && $order->gift_card_id !== null) {
            $giftRemaining = $this->giftCardRemainingToRestore($order);

            if (bccomp($giftRemaining, '0', 2) > 0) {
                $restoreAmount = $remainingBudget !== null
                    ? (bccomp($giftRemaining, $remainingBudget, 2) > 0 ? $remainingBudget : $giftRemaining)
                    : $giftRemaining;

                if (bccomp($restoreAmount, '0', 2) > 0) {
                    $giftCard = GiftCard::query()->find($order->gift_card_id);

                    if ($giftCard !== null) {
                        $this->giftCards->restoreFromRefund(
                            $giftCard,
                            $restoreAmount,
                            $order,
                            'Refund restored for order '.$order->order_number,
                        );

                        $restored['gift_card'] = $restoreAmount;

                        if ($remainingBudget !== null) {
                            $remainingBudget = Money::sub($remainingBudget, $restoreAmount);
                        }
                    }
                }
            }
        }

        if (Schema::hasColumn('orders', 'store_credit_amount')) {
            $creditRemaining = $this->storeCreditRemainingToRestore($order);

            if (bccomp($creditRemaining, '0', 2) > 0) {
                $restoreAmount = $remainingBudget !== null
                    ? (bccomp($creditRemaining, $remainingBudget, 2) > 0 ? $remainingBudget : $creditRemaining)
                    : $creditRemaining;

                if (bccomp($restoreAmount, '0', 2) > 0) {
                    $this->storeCredit->restoreForOrder($order, $restoreAmount);
                    $restored['store_credit'] = $restoreAmount;
                }
            }
        }

        return $restored;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function markRefundFailed(Refund $refund, array $metadata): void
    {
        $refund->status = RefundStatus::Failed;
        $refund->metadata = $metadata;
        $refund->save();
    }

    protected function prepaidTotal(Order $order): string
    {
        return Money::add(
            $this->giftCardSnapshot($order),
            $this->storeCreditSnapshot($order),
        );
    }

    protected function prepaidRemaining(Order $order): string
    {
        return Money::add(
            $this->giftCardRemainingToRestore($order),
            $this->storeCreditRemainingToRestore($order),
        );
    }

    protected function prepaidFundingAlreadyRestored(Order $order): bool
    {
        return bccomp($this->prepaidRemaining($order), '0', 2) <= 0
            && bccomp($this->prepaidTotal($order), '0', 2) > 0;
    }

    protected function giftCardSnapshot(Order $order): string
    {
        if (! Schema::hasColumn('orders', 'gift_card_amount')) {
            return '0.00';
        }

        return Money::add((string) ($order->gift_card_amount ?? '0.00'), '0');
    }

    protected function storeCreditSnapshot(Order $order): string
    {
        if (! Schema::hasColumn('orders', 'store_credit_amount')) {
            return '0.00';
        }

        return Money::add((string) ($order->store_credit_amount ?? '0.00'), '0');
    }

    protected function giftCardRestoredAmount(Order $order): string
    {
        if (! Schema::hasTable('gift_card_transactions')) {
            return '0.00';
        }

        return Money::add((string) GiftCardTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', GiftCardTransactionType::RefundRestore)
            ->sum('amount'), '0');
    }

    protected function storeCreditRestoredAmount(Order $order): string
    {
        if (! Schema::hasTable('store_credit_transactions')) {
            return '0.00';
        }

        return Money::add((string) StoreCreditTransaction::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->getKey())
            ->where('type', StoreCreditTransactionType::Refund)
            ->sum('amount'), '0');
    }

    protected function giftCardRemainingToRestore(Order $order): string
    {
        $remaining = Money::sub($this->giftCardSnapshot($order), $this->giftCardRestoredAmount($order));

        return bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';
    }

    protected function storeCreditRemainingToRestore(Order $order): string
    {
        $remaining = Money::sub($this->storeCreditSnapshot($order), $this->storeCreditRestoredAmount($order));

        return bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';
    }

    protected function syncOrderPaymentStatus(Order $order, ?OrderPayment $payment = null): void
    {
        $gatewayRemaining = $payment !== null
            ? $this->refundableAmount($payment)
            : $this->gatewayRefundableTotal($order);
        $prepaidRemaining = $this->prepaidRemaining($order);

        if (bccomp($gatewayRemaining, '0', 2) === 0 && bccomp($prepaidRemaining, '0', 2) === 0) {
            $order->payment_status = OrderPaymentStatus::Refunded;
        } else {
            $order->payment_status = OrderPaymentStatus::PartiallyRefunded;
        }

        $order->save();
    }

    protected function gatewayRefundableTotal(Order $order): string
    {
        $total = '0.00';

        $payments = OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('status', OrderPaymentRecordStatus::Successful)
            ->get();

        foreach ($payments as $payment) {
            $total = Money::add($total, $this->refundableAmount($payment));
        }

        return $total;
    }

    /**
     * Remaining refundable amount excluding completed and in-flight processing refunds.
     */
    protected function refundableAmount(OrderPayment $payment): string
    {
        $refunded = Refund::query()
            ->where('order_payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Completed, RefundStatus::Processing])
            ->sum('amount');

        return Money::sub((string) $payment->amount, (string) $refunded);
    }
}
