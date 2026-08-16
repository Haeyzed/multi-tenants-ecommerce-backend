<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Http\Requests\BaseRequest;

class OpenPosSessionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pos_terminal_id' => ['required', 'integer', 'exists:pos_terminals,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
