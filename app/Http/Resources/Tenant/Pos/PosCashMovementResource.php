<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Pos;

use App\Models\Tenant\PosCashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosCashMovement
 */
class PosCashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PosCashMovement $movement */
        $movement = $this->resource;

        return [
            'id' => $movement->id,
            'pos_session_id' => $movement->pos_session_id,
            'type' => $movement->type?->value,
            'amount' => $movement->amount,
            'reason' => $movement->reason,
            'user_id' => $movement->user_id,
            'created_at' => $movement->created_at,
            'updated_at' => $movement->updated_at,
        ];
    }
}
