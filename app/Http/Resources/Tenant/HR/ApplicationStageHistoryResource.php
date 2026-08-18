<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\ApplicationStageHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationStageHistory
 */
class ApplicationStageHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApplicationStageHistory $history */
        $history = $this->resource;

        return [
            'id' => $history->id,
            'from_stage_id' => $history->from_stage_id,
            'to_stage_id' => $history->to_stage_id,
            'from_status' => $history->from_status,
            'to_status' => $history->to_status,
            'changed_by' => $history->changed_by,
            'notes' => $history->notes,
            'created_at' => $history->created_at,
        ];
    }
}
