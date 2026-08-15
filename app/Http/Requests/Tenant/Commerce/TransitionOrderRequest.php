<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates admin order status transition.
 */
class TransitionOrderRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(OrderStatus::class)],
        ];
    }
}
