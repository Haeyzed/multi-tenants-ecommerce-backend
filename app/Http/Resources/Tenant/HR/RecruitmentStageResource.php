<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\RecruitmentStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecruitmentStage
 */
class RecruitmentStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RecruitmentStage $stage */
        $stage = $this->resource;

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'slug' => $stage->slug,
            'kind' => $stage->kind,
            'sort_order' => $stage->sort_order,
            'is_default' => $stage->is_default,
            'is_terminal' => $stage->is_terminal,
            'created_at' => $stage->created_at,
            'updated_at' => $stage->updated_at,
        ];
    }
}
