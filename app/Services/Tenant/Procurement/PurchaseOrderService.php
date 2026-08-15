<?php

declare(strict_types=1);

namespace App\Services\Tenant\Procurement;

use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use App\Models\Tenant\PurchaseOrder;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase order draft, approve, and mark ordered lifecycle.
 */
class PurchaseOrderService
{
    /**
     * @param  array{status?: string|null, supplier_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'warehouse'])
            ->latest('id');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['supplier_id'])) {
            $query->where('supplier_id', (int) $params['supplier_id']);
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Create a draft purchase order with line items.
     *
     * @param  array{
     *     supplier_id: int,
     *     warehouse_id: int,
     *     currency?: string,
     *     expected_at?: string|null,
     *     notes?: string|null,
     *     items: list<array{
     *         product_id: int,
     *         product_variant_id?: int|null,
     *         quantity: int,
     *         unit_cost: string|float,
     *         tax?: string|float|null
     *     }>
     * }  $data
     */
    public function create(array $data): PurchaseOrder
    {
        if (($data['items'] ?? []) === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one purchase order item is required.',
            ]);
        }

        return DB::transaction(function () use ($data): PurchaseOrder {
            $subtotal = '0.00';
            $taxTotal = '0.00';
            $prepared = [];

            foreach ($data['items'] as $item) {
                $qty = (int) $item['quantity'];
                $unitCost = Money::add((string) $item['unit_cost'], '0');
                $tax = Money::add((string) ($item['tax'] ?? '0'), '0');
                $lineTotal = Money::add(Money::mul($unitCost, (string) $qty), $tax);

                $subtotal = Money::add($subtotal, Money::mul($unitCost, (string) $qty));
                $taxTotal = Money::add($taxTotal, $tax);

                $prepared[] = [
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $qty,
                    'received_quantity' => 0,
                    'unit_cost' => $unitCost,
                    'tax' => $tax,
                    'total' => $lineTotal,
                ];
            }

            $grandTotal = Money::add($subtotal, $taxTotal);

            $po = PurchaseOrder::query()->create([
                'order_number' => $this->nextOrderNumber(),
                'supplier_id' => (int) $data['supplier_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'currency' => strtoupper((string) ($data['currency'] ?? 'NGN')),
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => '0.00',
                'shipping_total' => '0.00',
                'grand_total' => $grandTotal,
                'expected_at' => $data['expected_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($prepared as $line) {
                $po->items()->create($line);
            }

            return $po->load(['items', 'supplier', 'warehouse']);
        });
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->load(['items.product', 'items.productVariant', 'supplier', 'warehouse', 'goodsReceipts']);
    }

    /**
     * @throws ValidationException
     */
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or pending approval purchase orders can be approved.',
                ]);
            }

            $locked->status = PurchaseOrderStatus::Approved;
            $locked->save();

            return $locked->fresh(['items', 'supplier', 'warehouse']) ?? $locked;
        });
    }

    /**
     * @throws ValidationException
     */
    public function markOrdered(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved purchase orders can be marked ordered.',
                ]);
            }

            $locked->status = PurchaseOrderStatus::Ordered;
            $locked->ordered_at = now();
            $locked->save();

            return $locked->fresh(['items', 'supplier', 'warehouse']) ?? $locked;
        });
    }

    protected function nextOrderNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (PurchaseOrder::query()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
