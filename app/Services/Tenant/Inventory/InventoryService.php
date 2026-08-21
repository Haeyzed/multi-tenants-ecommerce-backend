<?php

declare(strict_types=1);

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseLocation;
use App\Services\Tenant\Commerce\BackInStockNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant inventory stock operations with movement audit trail.
 */
class InventoryService
{
    public function __construct(
        private readonly BackInStockNotificationService $backInStock,
        private readonly InventoryStockableResolver $stockables,
    ) {}

    /**
     * Paginate inventory records with warehouse and inventoryable loaded.
     *
     * @param  array{
     *     warehouse_id?: int|null,
     *     warehouse_location_id?: int|null,
     *     product_id?: int|null,
     *     product_variant_id?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Inventory>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with(['warehouse', 'inventoryable'])
            ->filter($params);

        $this->constrainCatalogue($query, $params);

        return $query
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
     * Assign a product or variant to a warehouse without duplicating the catalogue record.
     *
     * @param  array{
     *     warehouse_location_id?: int|null,
     *     quantity?: int|null,
     *     reorder_level?: int|null,
     *     reorder_quantity?: int|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function assign(
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant = null,
        array $data = [],
        ?User $actor = null,
    ): Inventory {
        if (! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Cannot assign inventory to an inactive warehouse.',
            ]);
        }

        $stockable = $this->stockables->resolve($product, $variant);
        $location = $this->resolveLocation($warehouse, $data['warehouse_location_id'] ?? null);

        try {
            $inventory = $this->getOrCreate($warehouse, $stockable, $location);
        } catch (UniqueConstraintViolationException) {
            $inventory = Inventory::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('inventoryable_type', $stockable->getMorphClass())
                ->where('inventoryable_id', $stockable->getKey())
                ->firstOrFail();
        }

        $updates = [];

        if (array_key_exists('reorder_level', $data)) {
            $updates['reorder_level'] = $data['reorder_level'];
        }

        if (array_key_exists('reorder_quantity', $data)) {
            $updates['reorder_quantity'] = $data['reorder_quantity'];
        }

        if (array_key_exists('warehouse_location_id', $data)) {
            $updates['warehouse_location_id'] = $location?->id;
        }

        if ($updates !== []) {
            $inventory->fill($updates);
            $inventory->save();
        }

        $opening = (int) ($data['quantity'] ?? 0);

        if ($opening > 0 && $inventory->quantity === 0 && $inventory->movements()->doesntExist()) {
            $inventory = $this->increase(
                $inventory,
                $opening,
                InventoryMovementType::OpeningStock,
                reason: 'Warehouse assignment',
                actor: $actor,
            );
        }

        return $this->show($inventory);
    }

    /**
     * Remove a zero-stock inventory assignment from a warehouse.
     *
     * @throws ValidationException
     */
    public function unassign(Inventory $inventory): void
    {
        if ($inventory->quantity > 0 || $inventory->reserved_quantity > 0) {
            throw ValidationException::withMessages([
                'inventory' => 'Cannot remove inventory that still has on-hand or reserved stock.',
            ]);
        }

        $inventory->delete();
    }

    /**
     * Resolve the catalogue record that should own warehouse inventory.
     *
     * @throws ValidationException
     */
    public function stockableFor(Product $product, ?ProductVariant $variant = null): Product|ProductVariant
    {
        return $this->stockables->resolve($product, $variant);
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
            $availableBefore = $quantityBefore - $locked->reserved_quantity;

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

            $availableAfter = $quantityAfter - $locked->reserved_quantity;
            $this->backInStock->handleInventoryChange($locked, $availableBefore, $availableAfter);

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
     * Atomically convert a reservation into an on-hand decrease (single lock).
     *
     * @throws ValidationException
     */
    public function commitReservation(
        Inventory $inventory,
        int $qty,
        InventoryMovementType $type,
        ?string $reason = null,
        ?string $notes = null,
        ?User $actor = null,
        ?Model $reference = null,
    ): Inventory {
        return DB::transaction(function () use ($inventory, $qty, $type, $reason, $notes, $actor, $reference): Inventory {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Commit quantity must be greater than zero.',
                ]);
            }

            if ($qty > $locked->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cannot commit more than the reserved quantity.',
                ]);
            }

            if ($qty > $locked->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient on-hand stock to commit reservation.',
                ]);
            }

            $quantityBefore = $locked->quantity;
            $quantityAfter = $quantityBefore - $qty;

            $locked->movements()->create([
                'type' => $type,
                'quantity' => -$qty,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);

            $locked->quantity = $quantityAfter;
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
     * @param  array{product_id?: int|null, product_variant_id?: int|null}  $params
     */
    protected function constrainCatalogue(Builder $query, array $params): void
    {
        $variantId = $params['product_variant_id'] ?? null;

        if ($variantId !== null) {
            $query->where('inventoryable_type', (new ProductVariant)->getMorphClass())
                ->where('inventoryable_id', (int) $variantId);

            return;
        }

        $productId = $params['product_id'] ?? null;

        if ($productId === null) {
            return;
        }

        $product = Product::query()->with('variants:id,product_id')->find((int) $productId);

        if ($product === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $variantIds = $product->variants->pluck('id')->all();

        $query->where(function (Builder $query) use ($product, $variantIds): void {
            $query->where(function (Builder $query) use ($product): void {
                $query->where('inventoryable_type', $product->getMorphClass())
                    ->where('inventoryable_id', $product->id);
            });

            if ($variantIds !== []) {
                $query->orWhere(function (Builder $query) use ($variantIds): void {
                    $query->where('inventoryable_type', (new ProductVariant)->getMorphClass())
                        ->whereIn('inventoryable_id', $variantIds);
                });
            }
        });
    }

    /**
     * @throws ValidationException
     */
    protected function resolveLocation(Warehouse $warehouse, mixed $locationId): ?WarehouseLocation
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $location = WarehouseLocation::query()->find((int) $locationId);

        if ($location === null || $location->warehouse_id !== $warehouse->id) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => 'The location does not belong to the selected warehouse.',
            ]);
        }

        return $location;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
