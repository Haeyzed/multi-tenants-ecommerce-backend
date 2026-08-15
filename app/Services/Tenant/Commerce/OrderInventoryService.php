<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Models\Tenant\Order;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Validation\ValidationException;

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
     * Release reserved stock for unpaid orders only.
     *
     * @throws ValidationException
     */
    public function releaseForOrder(Order $order): void
    {
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => 'Cannot release reservations for a paid order. Use a refund flow instead.',
            ]);
        }

        $order->loadMissing('items.inventory');

        foreach ($order->items as $item) {
            if ($item->inventory_id === null || $item->inventory === null) {
                continue;
            }

            if ($item->inventory->reserved_quantity < $item->quantity) {
                continue;
            }

            $this->inventoryService->release($item->inventory, $item->quantity);
        }
    }

    /**
     * Atomically convert reserved stock into a sale decrease for each reserved line.
     */
    public function commitSaleForOrder(Order $order): void
    {
        $order->loadMissing('items.inventory');

        foreach ($order->items as $item) {
            if ($item->inventory_id === null || $item->inventory === null) {
                continue;
            }

            $this->inventoryService->commitReservation(
                $item->inventory,
                $item->quantity,
                InventoryMovementType::Sale,
                reason: 'Order sale',
                reference: $order,
            );
        }
    }
}
