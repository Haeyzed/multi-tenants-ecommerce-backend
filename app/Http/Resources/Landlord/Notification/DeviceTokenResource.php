<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Notification;

use App\Models\Notification\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceToken
 */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DeviceToken $token */
        $token = $this->resource;

        return [
            'id' => $token->id,
            'device_type' => $token->device_type->value,
            'device_token' => $token->device_token,
            'provider' => $token->provider,
            'app_version' => $token->app_version,
            'last_used_at' => $token->last_used_at,
            'is_active' => (bool) $token->is_active,
            'created_at' => $token->created_at,
            'updated_at' => $token->updated_at,
        ];
    }
}
