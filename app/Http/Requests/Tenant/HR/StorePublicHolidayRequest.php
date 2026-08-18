<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StorePublicHolidayRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_on' => ['required', 'date', Rule::unique('public_holidays', 'observed_on')],
            'name' => ['required', 'string', 'max:255'],
            'repeats_annually' => ['sometimes', 'boolean'],
        ];
    }
}
