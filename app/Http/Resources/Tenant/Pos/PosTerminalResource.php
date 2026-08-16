<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Pos;

use App\Models\Tenant\PosTerminal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosTerminal
 */
class PosTerminalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PosTerminal $terminal */
        $terminal = $this->resource;

        return [
            'id' => $terminal->id,
            'name' => $terminal->name,
            'code' => $terminal->code,
            'status' => $terminal->status?->value,
            'warehouse_id' => $terminal->warehouse_id,
            'location_label' => $terminal->location_label,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $terminal->warehouse?->id,
                'name' => $terminal->warehouse?->name,
            ]),
            'open_session_id' => $this->whenLoaded('openSession', fn () => $terminal->openSession?->id),
            'created_at' => $terminal->created_at,
            'updated_at' => $terminal->updated_at,
        ];
    }
}
