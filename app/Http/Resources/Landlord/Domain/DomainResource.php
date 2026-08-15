<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Domain;

use App\Models\Landlord\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant domains.
 *
 * @mixin Domain
 */
class DomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Domain $domain */
        $domain = $this->resource;

        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'tenant_id' => $domain->tenant_id,
            'is_primary' => (bool) $domain->is_primary,
            'created_at' => $domain->created_at,
            'updated_at' => $domain->updated_at,
        ];
    }
}
