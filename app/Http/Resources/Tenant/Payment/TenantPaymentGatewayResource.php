<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public tenant payment gateway representation (secrets masked / never raw).
 *
 * @property array<string, mixed> $resource
 */
class TenantPaymentGatewayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => $data['id'] ?? null,
            'gateway' => $data['gateway'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}
