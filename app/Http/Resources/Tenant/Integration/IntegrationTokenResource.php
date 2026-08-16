<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Integration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @mixin PersonalAccessToken
 */
class IntegrationTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        $label = $token->name;
        $prefix = 'integration:';
        if (str_starts_with($label, $prefix)) {
            $label = substr($label, strlen($prefix));
        }

        return [
            'id' => $token->id,
            'name' => $label,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at,
        ];
    }
}
