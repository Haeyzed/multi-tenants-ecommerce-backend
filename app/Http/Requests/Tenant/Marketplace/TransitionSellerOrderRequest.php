<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates seller order status transition.
 */
class TransitionSellerOrderRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(SellerOrderStatus::class)],
        ];
    }
}
