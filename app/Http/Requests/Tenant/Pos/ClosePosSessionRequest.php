<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Http\Requests\BaseRequest;

class ClosePosSessionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
