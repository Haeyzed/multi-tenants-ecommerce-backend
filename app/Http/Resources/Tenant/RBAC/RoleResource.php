<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * API resource for tenant roles.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the role resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->values()->all()
                : [],
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }
}
