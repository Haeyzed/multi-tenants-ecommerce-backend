<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Driver;

use App\Models\Tenant\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for drivers.
 *
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Driver $driver */
        $driver = $this->resource;

        return [
            'id' => $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'full_name' => $driver->full_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'status' => $driver->status?->value,
            'availability' => $driver->availability?->value,
            'email_verified_at' => $driver->email_verified_at,
            'last_login_at' => $driver->last_login_at,
            'deleted_at' => $driver->deleted_at,
            'created_at' => $driver->created_at,
            'updated_at' => $driver->updated_at,
        ];
    }
}
