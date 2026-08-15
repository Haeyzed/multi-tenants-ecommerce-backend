<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Inventory;

use App\Models\Tenant\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for inventory movement audit records.
 *
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InventoryMovement $movement */
        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'inventory_id' => $movement->inventory_id,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
            'quantity_before' => $movement->quantity_before,
            'quantity_after' => $movement->quantity_after,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'reason' => $movement->reason,
            'notes' => $movement->notes,
            'created_by' => $movement->created_by,
            'created_at' => $movement->created_at,
            'updated_at' => $movement->updated_at,
        ];
    }
}
