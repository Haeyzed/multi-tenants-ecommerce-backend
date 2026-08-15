<?php

declare(strict_types=1);

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant inventory stock operations with movement audit trail.
 */
class InventoryService
{
    /**
     * Paginate inventory records with warehouse and inventoryable loaded.
     *
     * @param  array{
     *     warehouse_id?: int|null,
     *     warehouse_location_id?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Inventory>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Inventory::query()
            ->with(['warehouse', 'inventoryable'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve an inventory record with relations.
     */
    public function show(Inventory $inventory): Inventory
    {
        return $inventory->load(['warehouse', 'warehouseLocation', 'inventoryable', 'movements']);
    }

    /**
     * Find or create an inventory row for a warehouse and inventoryable.
     */
    public function getOrCreate(
        Warehouse $warehouse,
        Model $inventoryable,
        ?WarehouseLocation $location = null,
    ): Inventory {
        return Inventory::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'inventoryable_type' => $inventoryable->getMorphClass(),
                'inventoryable_id' => $inventoryable->getKey(),
            ],
            [
                'warehouse_location_id' => $location?->id,
                'quantity' => 0,
                'reserved_quantity' => 0,
            ],
        );
    }

    /**
     * Adjust inventory quantity and record a movement.
     *
     * @throws ValidationException
     */
    public function adjust(
        Inventory $inventory,
        int $delta,
        InventoryMovementType $type,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?Model $reference = null,
    ): Inventory {
        return DB::transaction(function () use ($inventory, $delta, $type, $reason, $notes, $actor, $reference): Inventory {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();

            $quantityBefore = $locked->quantity;
            $quantityAfter = $quantityBefore + $delta;

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock for this adjustment.',
                ]);
            }

            if ($locked->reserved_quantity > $quantityAfter) {
                throw ValidationException::withMessages([
                    'quantity' => 'Adjustment would leave reserved quantity exceeding on-hand stock.',
                ]);
            }

            $locked->movements()->create([
                'type' => $type,
                'quantity' => $delta,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);

            $locked->quantity = $quantityAfter;
            $locked->save();

            return $locked->fresh(['warehouse', 'inventoryable']) ?? $locked;
        });
    }

    /**
     * Increase inventory quantity.
     */
    public function increase(
        Inventory $inventory,
        int $quantity,
        InventoryMovementType $type,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?Model $reference = null,
    ): Inventory {
        return $this->adjust($inventory, abs($quantity), $type, $reason, $notes, $actor, $reference);
    }

    /**
     * Decrease inventory quantity.
     */
    public function decrease(
        Inventory $inventory,
        int $quantity,
        InventoryMovementType $type,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?Model $reference = null,
    ): Inventory {
        return $this->adjust($inventory, -abs($quantity), $type, $reason, $notes, $actor, $reference);
    }

    /**
     * Reserve stock for an order or hold.
     *
     * @throws ValidationException
     */
    public function reserve(Inventory $inventory, int $qty): Inventory
    {
        return DB::transaction(function () use ($inventory, $qty): Inventory {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Reserve quantity must be greater than zero.',
                ]);
            }

            if ($qty > $locked->availableQuantity()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cannot reserve more than available stock.',
                ]);
            }

            $locked->reserved_quantity += $qty;
            $locked->save();

            return $locked->fresh(['warehouse', 'inventoryable']) ?? $locked;
        });
    }

    /**
     * Release previously reserved stock.
     *
     * @throws ValidationException
     */
    public function release(Inventory $inventory, int $qty): Inventory
    {
        return DB::transaction(function () use ($inventory, $qty): Inventory {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Release quantity must be greater than zero.',
                ]);
            }

            if ($qty > $locked->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cannot release more than the reserved quantity.',
                ]);
            }

            $locked->reserved_quantity -= $qty;
            $locked->save();

            return $locked->fresh(['warehouse', 'inventoryable']) ?? $locked;
        });
    }

    /**
     * Transfer stock between warehouses for the same inventoryable.
     *
     * @throws ValidationException
     */
    public function transfer(
        Inventory $from,
        Warehouse $toWarehouse,
        int $qty,
        ?User $actor = null,
    ): array {
        if ($from->warehouse_id === $toWarehouse->id) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Destination warehouse must differ from the source warehouse.',
            ]);
        }

        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer quantity must be greater than zero.',
            ]);
        }

        if ($qty > $from->availableQuantity()) {
            throw ValidationException::withMessages([
                'quantity' => 'Cannot transfer more than available stock.',
            ]);
        }

        return DB::transaction(function () use ($from, $toWarehouse, $qty, $actor): array {
            $from->loadMissing('inventoryable');
            $inventoryable = $from->inventoryable;

            if ($inventoryable === null) {
                throw ValidationException::withMessages([
                    'inventory' => 'Inventory record has no associated product or variant.',
                ]);
            }

            $fromInventory = $this->decrease(
                $from,
                $qty,
                InventoryMovementType::TransferOut,
                reason: 'Stock transfer out',
                actor: $actor,
            );

            $toInventory = $this->getOrCreate(
                $toWarehouse,
                $inventoryable,
            );

            $toInventory = $this->increase(
                $toInventory,
                $qty,
                InventoryMovementType::TransferIn,
                reason: 'Stock transfer in',
                actor: $actor,
            );

            return [
                'from' => $fromInventory,
                'to' => $toInventory,
            ];
        });
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
