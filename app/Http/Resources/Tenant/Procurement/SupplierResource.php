<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Procurement;

use App\Models\Tenant\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->resource;

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'code' => $supplier->code,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'website' => $supplier->website,
            'tax_number' => $supplier->tax_number,
            'status' => $supplier->status,
            'address_line_1' => $supplier->address_line_1,
            'address_line_2' => $supplier->address_line_2,
            'country_id' => $supplier->country_id,
            'state_id' => $supplier->state_id,
            'city_id' => $supplier->city_id,
            'postal_code' => $supplier->postal_code,
            'notes' => $supplier->notes,
            'contacts' => $this->whenLoaded('contacts', fn () => $supplier->contacts->map(fn ($contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'is_primary' => $contact->is_primary,
            ])->values()->all()),
            'created_at' => $supplier->created_at,
            'updated_at' => $supplier->updated_at,
            'deleted_at' => $supplier->deleted_at,
        ];
    }
}
