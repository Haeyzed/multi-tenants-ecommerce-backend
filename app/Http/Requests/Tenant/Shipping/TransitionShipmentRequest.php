<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Shipping;

use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class TransitionShipmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ShipmentStatus::class)],
        ];
    }
}
