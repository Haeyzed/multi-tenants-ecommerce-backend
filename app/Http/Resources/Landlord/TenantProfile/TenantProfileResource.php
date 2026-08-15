<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\TenantProfile;

use App\Models\Landlord\TenantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for landlord tenant profiles.
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
            'id' => $profile->id,
            'tenant_id' => $profile->tenant_id,
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'description' => $profile->description,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'website' => $profile->website,
            'address' => $profile->address,
            'country_id' => $profile->country_id,
            'state_id' => $profile->state_id,
            'city_id' => $profile->city_id,
            'currency_id' => $profile->currency_id,
            'language_id' => $profile->language_id,
            'timezone' => $profile->timezone,
            'is_public' => (bool) $profile->is_public,
            'logo' => $profile->logo_url,
            'cover' => $profile->cover_url,
            'created_at' => $profile->created_at,
            'updated_at' => $profile->updated_at,
        ];
    }
}
