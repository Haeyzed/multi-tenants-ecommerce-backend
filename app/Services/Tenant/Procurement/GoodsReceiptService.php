<?php

declare(strict_types=1);

namespace App\Services\Tenant\Procurement;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receive goods against purchase orders with inventory and accounting posts.
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AccountingService $accounting,
    ) {}

    /**
     * Receive quantities against a purchase order (supports partial receives).
     *
     * @param  list<array{purchase_order_item_id: int, quantity: int}>  $items
     *
     * @throws ValidationException
     */
    public function receive(PurchaseOrder $purchaseOrder, array $items, ?User $actor = null, ?string $notes = null): GoodsReceipt
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one receipt line is required.',
            ]);
        }

        $allowedStatuses = [
            PurchaseOrderStatus::Ordered,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Approved,
        ];

        if (! in_array($purchaseOrder->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order cannot receive goods in its current status.',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $items, $actor, $notes): GoodsReceipt {
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            $po->loadMissing(['items', 'warehouse']);

            /** @var Warehouse $warehouse */
            $warehouse = $po->warehouse;

            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->nextReceiptNumber(),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'received_at' => now(),
                'notes' => $notes,
                'received_by' => $actor?->id,
            ]);

            foreach ($items as $index => $line) {
                $itemId = (int) $line['purchase_order_item_id'];
                $qty = (int) $line['quantity'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Quantity must be greater than zero.',
                    ]);
                }

                /** @var PurchaseOrderItem|null $poItem */
                $poItem = $po->items->firstWhere('id', $itemId);

                if ($poItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.purchase_order_item_id" => 'Purchase order item not found on this order.',
                    ]);
                }

                $remaining = $poItem->quantity - $poItem->received_quantity;

                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Cannot receive more than remaining quantity ({$remaining}).",
                    ]);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'quantity' => $qty,
                    'unit_cost' => $poItem->unit_cost,
                ]);

                $poItem->received_quantity += $qty;
                $poItem->save();

                $inventoryable = $poItem->product_variant_id !== null
                    ? ProductVariant::query()->findOrFail($poItem->product_variant_id)
                    : Product::query()->findOrFail($poItem->product_id);

                $inventory = $this->inventoryService->getOrCreate($warehouse, $inventoryable);
                $this->inventoryService->increase(
                    $inventory,
                    $qty,
                    InventoryMovementType::Purchase,
                    reason: 'Goods receipt '.$receipt->receipt_number,
                    reference: $receipt,
                    actor: $actor,
                );
            }

            $po->load('items');
            $allReceived = $po->items->every(fn (PurchaseOrderItem $item): bool => $item->received_quantity >= $item->quantity);
            $anyReceived = $po->items->contains(fn (PurchaseOrderItem $item): bool => $item->received_quantity > 0);

            $po->status = $allReceived
                ? PurchaseOrderStatus::Received
                : ($anyReceived ? PurchaseOrderStatus::PartiallyReceived : $po->status);
            $po->save();

            $receipt = $receipt->fresh(['items', 'purchaseOrder', 'warehouse']) ?? $receipt;
            $this->accounting->postGoodsReceipt($receipt);

            return $receipt;
        });
    }

    public function show(GoodsReceipt $receipt): GoodsReceipt
    {
        return $receipt->load(['items', 'purchaseOrder', 'warehouse', 'receiver']);
    }

    protected function nextReceiptNumber(): string
    {
        do {
            $number = 'GR-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (GoodsReceipt::query()->where('receipt_number', $number)->exists());

        return $number;
    }
}
