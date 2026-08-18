<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobOffer
 */
class JobOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JobOffer $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'job_application_id' => $offer->job_application_id,
            'position' => $offer->position,
            'salary' => $offer->salary,
            'currency' => $offer->currency,
            'start_date' => $offer->start_date?->toDateString(),
            'expires_at' => $offer->expires_at?->toDateString(),
            'status' => $offer->status,
            'notes' => $offer->notes,
            'approved_by' => $offer->approved_by,
            'sent_at' => $offer->sent_at,
            'decided_at' => $offer->decided_at,
            'created_at' => $offer->created_at,
            'updated_at' => $offer->updated_at,
        ];
    }
}
