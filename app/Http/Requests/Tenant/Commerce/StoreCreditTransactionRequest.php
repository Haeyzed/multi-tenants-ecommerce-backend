<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;
use App\Rules\MoneyAmount;

class StoreCreditTransactionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', new MoneyAmount],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
