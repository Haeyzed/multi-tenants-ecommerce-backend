<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Pos;

use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdatePosTerminalRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $terminalId = $this->route('pos_terminal')?->id ?? $this->route('pos_terminal');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('pos_terminals', 'code')->ignore($terminalId)],
            'status' => ['sometimes', 'string', Rule::enum(PosTerminalStatus::class)],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'location_label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
