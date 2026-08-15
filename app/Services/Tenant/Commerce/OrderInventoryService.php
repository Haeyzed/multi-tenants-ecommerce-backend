<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Models\Tenant\Order;
use App\Services\Tenant\Inventory\InventoryService;

/**
 * Inventory reservation and sale commit for orders.
 */
class OrderInventoryService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Reserve stock for all order items that have an inventory_id.
     */
    public function reserveForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->inventory_id === null) {
                continue;
            }

            $item->loadMissing('inventory');

            if ($item->inventory === null) {
                continue;
            }

            $this->inventoryService->reserve($item->inventory, $item->quantity);
        }
    }

    /**
     * Release reserved stock for order items with inventory_id.
     */
    public function releaseForOrder(Order $order): void
    {
        $order->loadMissing('items.inventory');

        foreach ($order->items as $item) {
            if ($item->inventory_id === null || $item->inventory === null) {
                continue;
            }

            $this->inventoryService->release($item->inventory, $item->quantity);
        }
    }

    /**
     * Convert reserved stock into a sale decrease for each reserved line.
     */
    public function commitSaleForOrder(Order $order): void
    {
        $order->loadMissing('items.inventory');

        foreach ($order->items as $item) {
            if ($item->inventory_id === null || $item->inventory === null) {
                continue;
            }

            $this->inventoryService->release($item->inventory, $item->quantity);
            $this->inventoryService->decrease(
                $item->inventory->fresh() ?? $item->inventory,
                $item->quantity,
                InventoryMovementType::Sale,
                reason: 'Order sale',
                reference: $order,
            );
        }
    }
}
