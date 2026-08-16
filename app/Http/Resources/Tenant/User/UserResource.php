<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\User;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant users.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the tenant user into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'seller_id' => $user->seller_id,
            'avatar' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
