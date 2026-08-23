<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Tenant;

use App\Enums\Landlord\TenantStatus;
use App\Http\Resources\Landlord\Domain\DomainResource;
use App\Http\Resources\Landlord\TenantProfile\TenantProfileResource;
use App\Models\Landlord\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for landlord tenants.
 *
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'status' => $tenant->status?->value ?? $tenant->status,
            'is_active' => (bool) $tenant->is_active,
            'is_provisioned' => $tenant->status !== TenantStatus::Pending,
            'provision_error' => $tenant->provision_error,
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'profile' => new TenantProfileResource($this->whenLoaded('profile')),
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
        ];
    }
}
