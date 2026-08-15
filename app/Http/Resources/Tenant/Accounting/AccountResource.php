<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Accounting;

use App\Models\Tenant\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'is_system' => (bool) $account->is_system,
            'is_active' => (bool) $account->is_active,
            'description' => $account->description,
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }
}
