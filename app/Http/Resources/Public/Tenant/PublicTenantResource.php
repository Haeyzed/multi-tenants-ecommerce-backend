<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Tenant;

use App\Http\Resources\Public\TenantProfile\TenantProfileResource;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenant\PublicTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe public tenant bootstrap payload for unauthenticated clients.
 *
 * @mixin Tenant
 */
class PublicTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;
        $profile = $tenant->profile;
        $primaryDomain = $tenant->domains
            ->sortByDesc(fn ($domain): bool => (bool) $domain->is_primary)
            ->first();

        $allowsLogin = app(PublicTenantResolver::class)->allowsLogin($tenant);

        return [
            'id' => (string) $tenant->getTenantKey(),
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status instanceof \BackedEnum
                ? $tenant->status->value
                : (string) $tenant->status,
            'is_active' => (bool) $tenant->is_active,
            'allows_login' => $allowsLogin,
            'domain' => $primaryDomain?->domain,
            'display_name' => $profile?->display_name ?? $tenant->name,
            'description' => $profile?->description,
            'logo' => $profile?->logo_url,
            'cover' => $profile?->cover_url,
            'favicon' => $profile?->logo_url,
            'timezone' => $profile?->timezone,
            'profile' => $profile !== null
                ? new TenantProfileResource($profile)
                : null,
        ];
    }
}
