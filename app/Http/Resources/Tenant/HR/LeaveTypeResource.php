<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeaveType $type */
        $type = $this->resource;

        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'is_paid' => $type->is_paid,
            'is_active' => $type->is_active,
            'default_days' => $type->default_days,
            'description' => $type->description,
            'created_at' => $type->created_at,
            'updated_at' => $type->updated_at,
        ];
    }
}
