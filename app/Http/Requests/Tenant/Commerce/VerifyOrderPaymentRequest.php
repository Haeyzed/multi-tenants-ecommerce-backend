<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Http\Requests\BaseRequest;

class VerifyOrderPaymentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:191'],
        ];
    }
}
