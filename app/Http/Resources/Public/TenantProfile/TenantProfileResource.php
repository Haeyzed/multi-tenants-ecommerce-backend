<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\TenantProfile;

use App\Models\Landlord\TenantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public API resource for tenant storefront profiles.
 *
 * @mixin TenantProfile
 */
class TenantProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TenantProfile $profile */
        $profile = $this->resource;

        return [
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'description' => $profile->description,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'website' => $profile->website,
            'address' => $profile->address,
            'timezone' => $profile->timezone,
            'logo' => $profile->logo_url,
            'cover' => $profile->cover_url,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $profile->country?->id,
                'name' => $profile->country?->name ?? null,
            ]),
            'state' => $this->whenLoaded('state', fn () => [
                'id' => $profile->state?->id,
                'name' => $profile->state?->name ?? null,
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $profile->city?->id,
                'name' => $profile->city?->name ?? null,
            ]),
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $profile->currency?->id,
                'code' => $profile->currency?->code ?? null,
                'symbol' => $profile->currency?->symbol ?? null,
            ]),
            'language' => $this->whenLoaded('language', fn () => [
                'id' => $profile->language?->id,
                'name' => $profile->language?->name ?? null,
                'code' => $profile->language?->code ?? null,
            ]),
        ];
    }
}
