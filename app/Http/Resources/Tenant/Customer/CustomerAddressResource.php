<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Customer;

use App\Models\Tenant\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for customer addresses.
 *
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CustomerAddress $address */
        $address = $this->resource;

        return [
            'id' => $address->id,
            'customer_id' => $address->customer_id,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'country_id' => $address->country_id,
            'state_id' => $address->state_id,
            'city_id' => $address->city_id,
            'postal_code' => $address->postal_code,
            'landmark' => $address->landmark,
            'is_default' => (bool) $address->is_default,
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ];
    }
}
