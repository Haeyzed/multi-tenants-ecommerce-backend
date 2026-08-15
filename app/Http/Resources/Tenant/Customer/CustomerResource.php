<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Customer;

use App\Models\Tenant\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for customers.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Customer $customer */
        $customer = $this->resource;

        return [
            'id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status?->value,
            'avatar' => $customer->avatar_url,
            'email_verified_at' => $customer->email_verified_at,
            'phone_verified_at' => $customer->phone_verified_at,
            'last_login_at' => $customer->last_login_at,
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'deleted_at' => $customer->deleted_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }
}
