<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Pos;

use App\Models\Tenant\PosSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosSession
 */
class PosSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PosSession $session */
        $session = $this->resource;

        return [
            'id' => $session->id,
            'pos_terminal_id' => $session->pos_terminal_id,
            'user_id' => $session->user_id,
            'status' => $session->status?->value,
            'opened_at' => $session->opened_at,
            'closed_at' => $session->closed_at,
            'opening_cash' => $session->opening_cash,
            'closing_cash' => $session->closing_cash,
            'expected_cash' => $session->expected_cash,
            'actual_cash' => $session->actual_cash,
            'cash_difference' => $session->cash_difference,
            'notes' => $session->notes,
            'terminal' => $this->whenLoaded('terminal', fn () => new PosTerminalResource($session->terminal)),
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
        ];
    }
}
