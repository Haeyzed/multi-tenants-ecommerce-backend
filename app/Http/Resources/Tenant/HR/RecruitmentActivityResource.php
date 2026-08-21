<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\RecruitmentActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecruitmentActivity
 */
class RecruitmentActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RecruitmentActivity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'subject_type' => class_basename($activity->subject_type),
            'subject_id' => $activity->subject_id,
            'action' => $activity->action,
            'meta' => $activity->meta,
            'actor' => $this->whenLoaded('actor', fn () => $activity->actor === null ? null : [
                'id' => $activity->actor->id,
                'first_name' => $activity->actor->first_name,
                'last_name' => $activity->actor->last_name,
            ]),
            'created_at' => $activity->created_at,
        ];
    }
}
