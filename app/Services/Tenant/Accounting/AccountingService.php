<?php

declare(strict_types=1);

namespace App\Services\Tenant\Accounting;

use App\Models\Tenant\Account;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Domain posting helpers for sales, goods receipts, and refunds.
 */
class AccountingService
{
    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly CommerceSettingService $commerceSettings,
    ) {}

    /**
     * Post a sale journal for an order (idempotent via entry_type=sale).
     */
    public function postSale(Order $order): ?JournalEntry
    {
        return $this->journals->postUnique($order, 'sale', function (JournalEntryService $journals) use ($order): JournalEntry {
            $cashId = $this->accountId('accounting.cash');
            $salesId = $this->accountId('accounting.sales');
            $taxId = $this->accountId('accounting.tax_payable');

            $grandTotal = Money::add((string) $order->grand_total, '0');
            $taxTotal = Money::add((string) $order->tax_total, '0');
            $salesAmount = Money::sub($grandTotal, $taxTotal);

            $lines = [
                [
                    'account_id' => $cashId,
                    'debit' => $grandTotal,
                    'credit' => '0.00',
                    'description' => 'Cash received for order '.$order->order_number,
                ],
                [
                    'account_id' => $salesId,
                    'debit' => '0.00',
                    'credit' => $salesAmount,
                    'description' => 'Sales revenue for order '.$order->order_number,
                ],
            ];

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
     * Post inventory / AP for a goods receipt (idempotent via entry_type=goods_receipt).
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

            $lines = $sale->lines->map(fn ($line): array => [
                'account_id' => $line->account_id,
                'debit' => (string) $line->credit,
                'credit' => (string) $line->debit,
                'description' => $line->description,
            ])->all();

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
     * Resolve a commerce setting account key to an account id (value may be id or code).
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
}
