<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public offer view for tokenized candidate responses. Omits internal notes and approval metadata.
 *
 * @mixin JobOffer
 */
class PublicJobOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JobOffer $offer */
        $offer = $this->resource;

        return [
            'position' => $offer->position,
            'salary' => $offer->salary,
            'currency' => $offer->currency,
            'start_date' => $offer->start_date?->toDateString(),
            'expires_at' => $offer->expires_at?->toDateString(),
            'status' => $offer->status,
        ];
    }
}
