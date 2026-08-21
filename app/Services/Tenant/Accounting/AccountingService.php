<?php

declare(strict_types=1);

namespace App\Services\Tenant\Accounting;

use App\Models\Tenant\Account;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Order;
use App\Models\Tenant\Refund;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerPayout;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Domain posting helpers for sales, goods receipts, and refunds.
 *
 * COGS posting is deferred: inventory unit cost is not trivially available on order items at sale time.
 */
class AccountingService
{
    /**
     * Create a new class instance.
     *
     * @param  JournalEntryService  $journals
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly CommerceSettingService $commerceSettings,
    ) {}

    /**
     * Post a sale journal for an order (idempotent via entry_type=sale).
     *
     * @param  Order  $order
     * @return ?JournalEntry
     */
    public function postSale(Order $order): ?JournalEntry
    {
        $order->loadMissing('items');

        if (bccomp($this->recognizedOrderTotal($order), '0', 2) <= 0) {
            return null;
        }

        if ($order->items->contains(fn ($item): bool => $item->seller_id !== null)) {
            return $this->postMarketplaceSale($order);
        }

        return $this->journals->postUnique($order, 'sale', function (JournalEntryService $journals) use ($order): JournalEntry {
            $salesId = $this->accountId('accounting.sales');
            $taxId = $this->accountId('accounting.tax_payable');

            $recognizedTotal = $this->recognizedOrderTotal($order);
            $taxTotal = Money::add((string) $order->tax_total, '0');
            $shippingTotal = Money::add((string) $order->shipping_total, '0');
            $salesAmount = Money::sub(Money::sub($recognizedTotal, $taxTotal), $shippingTotal);

            $lines = $this->tenderDebitLines($order, 'order '.$order->order_number);

            if (bccomp($salesAmount, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $salesId,
                    'debit' => '0.00',
                    'credit' => $salesAmount,
                    'description' => 'Sales revenue for order '.$order->order_number,
                ];
            }

            if (bccomp($shippingTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $salesId,
                    'debit' => '0.00',
                    'credit' => $shippingTotal,
                    'description' => 'Shipping revenue for order '.$order->order_number,
                ];
            }

            if (bccomp($taxTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $taxId,
                    'debit' => '0.00',
                    'credit' => $taxTotal,
                    'description' => 'Tax payable for order '.$order->order_number,
                ];
            }

            return $journals->createDraft(
                reference: 'JE-SALE-'.$order->order_number,
                description: 'Sale for order '.$order->order_number,
                entryDate: now()->toDateString(),
                lines: $lines,
                source: $order,
                entryType: 'sale',
            );
        });
    }

    /**
     * Post a marketplace sale journal splitting seller payable and commission revenue.
     *
     * @param  Order  $order
     * @return ?JournalEntry
     */
    public function postMarketplaceSale(Order $order): ?JournalEntry
    {
        return $this->journals->postUnique($order, 'sale', function (JournalEntryService $journals) use ($order): JournalEntry {
            $sellerPayableId = $this->accountId('accounting.seller_payable');
            $commissionRevenueId = $this->accountId('accounting.commission_revenue');
            $salesId = $this->accountId('accounting.sales');
            $taxId = $this->accountId('accounting.tax_payable');

            $commissions = SellerCommission::query()
                ->where('order_id', $order->id)
                ->get();

            $sellerPayableTotal = '0.00';
            $commissionTotal = '0.00';

            foreach ($commissions as $commission) {
                $sellerPayableTotal = Money::add($sellerPayableTotal, (string) $commission->seller_amount);
                $commissionTotal = Money::add($commissionTotal, (string) $commission->commission_amount);
            }

            $taxTotal = Money::add((string) $order->tax_total, '0');
            $shippingTotal = Money::add((string) $order->shipping_total, '0');

            $lines = $this->tenderDebitLines($order, 'marketplace order '.$order->order_number);

            if (bccomp($sellerPayableTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $sellerPayableId,
                    'debit' => '0.00',
                    'credit' => $sellerPayableTotal,
                    'description' => 'Seller payable for order '.$order->order_number,
                ];
            }

            if (bccomp($commissionTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $commissionRevenueId,
                    'debit' => '0.00',
                    'credit' => $commissionTotal,
                    'description' => 'Commission revenue for order '.$order->order_number,
                ];
            }

            if (bccomp($shippingTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $salesId,
                    'debit' => '0.00',
                    'credit' => $shippingTotal,
                    'description' => 'Shipping revenue for order '.$order->order_number,
                ];
            }

            if (bccomp($taxTotal, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $taxId,
                    'debit' => '0.00',
                    'credit' => $taxTotal,
                    'description' => 'Tax payable for order '.$order->order_number,
                ];
            }

            $lines = $this->balanceMarketplaceResidual($lines, $salesId, $order->order_number);

            return $journals->createDraft(
                reference: 'JE-SALE-'.$order->order_number,
                description: 'Marketplace sale for order '.$order->order_number,
                entryDate: now()->toDateString(),
                lines: $lines,
                source: $order,
                entryType: 'sale',
            );
        });
    }

    /**
     * Post a seller payout journal (debit seller payable, credit cash).
     *
     * @param  SellerPayout  $payout
     * @return ?JournalEntry
     */
    public function postPayout(SellerPayout $payout): ?JournalEntry
    {
        return $this->journals->postUnique($payout, 'payout', function (JournalEntryService $journals) use ($payout): JournalEntry {
            $cashId = $this->accountId('accounting.cash');
            $sellerPayableId = $this->accountId('accounting.seller_payable');
            $amount = Money::add((string) $payout->amount, '0');

            $lines = [
                [
                    'account_id' => $sellerPayableId,
                    'debit' => $amount,
                    'credit' => '0.00',
                    'description' => 'Seller payout '.$payout->reference,
                ],
                [
                    'account_id' => $cashId,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => 'Cash disbursed for seller payout '.$payout->reference,
                ],
            ];

            return $journals->createDraft(
                reference: 'JE-PAYOUT-'.$payout->id,
                description: 'Seller payout '.$payout->reference,
                entryDate: now()->toDateString(),
                lines: $lines,
                source: $payout,
                entryType: 'payout',
            );
        });
    }

    /**
     * Post inventory / AP for a goods receipt (idempotent via entry_type=goods_receipt).
     *
     * @param  GoodsReceipt  $receipt
     * @return ?JournalEntry
     */
    public function postGoodsReceipt(GoodsReceipt $receipt): ?JournalEntry
    {
        $receipt->loadMissing('items');

        return $this->journals->postUnique($receipt, 'goods_receipt', function (JournalEntryService $journals) use ($receipt): JournalEntry {
            $inventoryId = $this->accountId('accounting.inventory');
            $apId = $this->accountId('accounting.ap');

            $totalCost = '0.00';
            foreach ($receipt->items as $item) {
                $lineCost = Money::mul((string) $item->unit_cost, (string) $item->quantity);
                $totalCost = Money::add($totalCost, $lineCost);
            }

            if (bccomp($totalCost, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Goods receipt has zero cost; cannot post journal.',
                ]);
            }

            $lines = [
                [
                    'account_id' => $inventoryId,
                    'debit' => $totalCost,
                    'credit' => '0.00',
                    'description' => 'Inventory from receipt '.$receipt->receipt_number,
                ],
                [
                    'account_id' => $apId,
                    'debit' => '0.00',
                    'credit' => $totalCost,
                    'description' => 'Accounts payable for receipt '.$receipt->receipt_number,
                ],
            ];

            return $journals->createDraft(
                reference: 'JE-GR-'.$receipt->receipt_number,
                description: 'Goods receipt '.$receipt->receipt_number,
                entryDate: ($receipt->received_at ?? now())->toDateString(),
                lines: $lines,
                source: $receipt,
                entryType: 'goods_receipt',
            );
        });
    }

    /**
     * Reverse the sale journal for an order refund (idempotent via entry_type=refund).
     *
     * @param  Order  $order
     * @return ?JournalEntry
     */
    public function postRefund(Order $order): ?JournalEntry
    {
        $sale = JournalEntry::query()
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->getKey())
            ->where('entry_type', 'sale')
            ->first();

        if ($sale === null) {
            return null;
        }

        return $this->journals->postUnique($order, 'refund', function (JournalEntryService $journals) use ($sale, $order): JournalEntry {
            $sale->loadMissing('lines');

            $lines = $sale->lines
                ->map(fn ($line): array => [
                    'account_id' => $line->account_id,
                    'debit' => (string) $line->credit,
                    'credit' => (string) $line->debit,
                    'description' => $line->description,
                ])
                ->filter(fn (array $line): bool => bccomp($line['debit'], '0', 2) > 0 || bccomp($line['credit'], '0', 2) > 0)
                ->values()
                ->all();

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'order' => 'Sale journal has no reversible amounts.',
                ]);
            }

            return $journals->createDraft(
                reference: 'JE-REFUND-'.$order->order_number,
                description: 'Refund for order '.$order->order_number,
                entryDate: now()->toDateString(),
                lines: $lines,
                source: $order,
                entryType: 'refund',
            );
        });
    }

    /**
     * Post a partial refund journal (idempotent per refund id, not per amount).
     *
     * @param  Order  $order
     * @param  string  $amount
     * @param  Refund  $refund
     * @return ?JournalEntry
     */
    public function postPartialRefund(Order $order, string $amount, Refund $refund): ?JournalEntry
    {
        $entryType = 'partial_refund_'.$refund->id;
        $referenceSuffix = (string) $refund->id;

        return $this->journals->postUnique($order, $entryType, function (JournalEntryService $journals) use ($order, $amount, $entryType, $referenceSuffix): JournalEntry {
            $cashId = $this->accountId('accounting.cash');
            $salesId = $this->accountId('accounting.sales');
            $taxId = $this->accountId('accounting.tax_payable');

            $recognizedTotal = $this->recognizedOrderTotal($order);
            $taxTotal = Money::add((string) $order->tax_total, '0');
            $shippingTotal = Money::add((string) $order->shipping_total, '0');

            if (bccomp($recognizedTotal, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'Order has no recognized total for a partial refund journal.',
                ]);
            }

            $ratioPercent = bcdiv(bcmul($amount, '100', 4), $recognizedTotal, 4);
            $refundTax = Money::percent($taxTotal, $ratioPercent);
            $refundShipping = Money::percent($shippingTotal, $ratioPercent);
            // Sales absorbs rounding so refundSales + refundTax + refundShipping === $amount.
            $refundSales = Money::sub(Money::sub($amount, $refundTax), $refundShipping);

            $lines = [
                [
                    'account_id' => $cashId,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => 'Partial refund cash for order '.$order->order_number,
                ],
            ];

            if (bccomp($refundSales, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $salesId,
                    'debit' => $refundSales,
                    'credit' => '0.00',
                    'description' => 'Partial sales reversal for order '.$order->order_number,
                ];
            }

            if (bccomp($refundTax, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $taxId,
                    'debit' => $refundTax,
                    'credit' => '0.00',
                    'description' => 'Partial tax reversal for order '.$order->order_number,
                ];
            }

            if (bccomp($refundShipping, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $salesId,
                    'debit' => $refundShipping,
                    'credit' => '0.00',
                    'description' => 'Partial shipping reversal for order '.$order->order_number,
                ];
            }

            return $journals->createDraft(
                reference: 'JE-PREFUND-'.$order->order_number.'-'.$referenceSuffix,
                description: 'Partial refund for order '.$order->order_number,
                entryDate: now()->toDateString(),
                lines: $lines,
                source: $order,
                entryType: $entryType,
            );
        });
    }

    /**
     * Resolve a commerce setting account key to an account id (value may be id or code).
     *
     * @param  string  $settingKey
     * @return int
     *
     * @throws RuntimeException
     */
    public function accountId(string $settingKey): int
    {
        $value = $this->commerceSettings->get($settingKey);

        if ($value === null || $value === '') {
            throw new RuntimeException("Commerce setting [{$settingKey}] is not configured.");
        }

        $query = Account::query()->where('is_active', true);

        if (ctype_digit($value)) {
            $account = (clone $query)->whereKey((int) $value)->first()
                ?? (clone $query)->where('code', $value)->first();
        } else {
            $account = $query->where('code', $value)->first();
        }

        if ($account === null) {
            throw new RuntimeException("Account for setting [{$settingKey}] with value [{$value}] was not found.");
        }

        return (int) $account->id;
    }

    /**
     * Economic total for journals: gateway amount due plus prepaid gift card / store credit.
     *
     * @param  Order  $order
     * @return string
     */
    protected function recognizedOrderTotal(Order $order): string
    {
        $total = Money::add((string) $order->grand_total, '0');

        if (Schema::hasColumn('orders', 'gift_card_amount')) {
            $total = Money::add($total, (string) ($order->gift_card_amount ?? '0.00'));
        }

        if (Schema::hasColumn('orders', 'store_credit_amount')) {
            $total = Money::add($total, (string) ($order->store_credit_amount ?? '0.00'));
        }

        return $total;
    }

    /**
     * Debit cash for gateway `grand_total` and liability accounts for prepaid tenders.
     *
     * @param  Order  $order
     * @param  string  $contextLabel
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    protected function tenderDebitLines(Order $order, string $contextLabel): array
    {
        $lines = [];
        $cashAmount = Money::add((string) $order->grand_total, '0');

        if (bccomp($cashAmount, '0', 2) > 0) {
            $lines[] = [
                'account_id' => $this->accountId('accounting.cash'),
                'debit' => $cashAmount,
                'credit' => '0.00',
                'description' => 'Cash received for '.$contextLabel,
            ];
        }

        if (Schema::hasColumn('orders', 'gift_card_amount')) {
            $giftCardAmount = Money::add((string) ($order->gift_card_amount ?? '0.00'), '0');
            if (bccomp($giftCardAmount, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $this->accountId('accounting.gift_card_liability'),
                    'debit' => $giftCardAmount,
                    'credit' => '0.00',
                    'description' => 'Gift card tender for '.$contextLabel,
                ];
            }
        }

        if (Schema::hasColumn('orders', 'store_credit_amount')) {
            $storeCreditAmount = Money::add((string) ($order->store_credit_amount ?? '0.00'), '0');
            if (bccomp($storeCreditAmount, '0', 2) > 0) {
                $lines[] = [
                    'account_id' => $this->accountId('accounting.store_credit_liability'),
                    'debit' => $storeCreditAmount,
                    'credit' => '0.00',
                    'description' => 'Store credit tender for '.$contextLabel,
                ];
            }
        }

        return $lines;
    }

    /**
     * Balance marketplace sale lines on sales revenue when credits do not match tender debits.
     *
     * @param  list<array{account_id: int, debit: string, credit: string, description: string}>  $lines
     * @param  int  $salesId
     * @param  string  $orderNumber
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    protected function balanceMarketplaceResidual(array $lines, int $salesId, string $orderNumber): array
    {
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $line) {
            $totalDebit = Money::add($totalDebit, $line['debit']);
            $totalCredit = Money::add($totalCredit, $line['credit']);
        }

        $gap = Money::sub($totalDebit, $totalCredit);

        if (bccomp($gap, '0', 2) > 0) {
            $lines[] = [
                'account_id' => $salesId,
                'debit' => '0.00',
                'credit' => $gap,
                'description' => 'Marketplace residual / platform revenue for order '.$orderNumber,
            ];
        } elseif (bccomp($gap, '0', 2) < 0) {
            $lines[] = [
                'account_id' => $salesId,
                'debit' => Money::sub('0.00', $gap),
                'credit' => '0.00',
                'description' => 'Marketplace residual balancing debit for order '.$orderNumber,
            ];
        }

        return $lines;
    }
}
