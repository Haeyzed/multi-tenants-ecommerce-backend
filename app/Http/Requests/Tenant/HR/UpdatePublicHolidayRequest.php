<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\PublicHoliday;
use Illuminate\Validation\Rule;

class UpdatePublicHolidayRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PublicHoliday $holiday */
        $holiday = $this->route('public_holiday');

        return [
            'observed_on' => ['sometimes', 'date', Rule::unique('public_holidays', 'observed_on')->ignore($holiday->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'repeats_annually' => ['sometimes', 'boolean'],
        ];
    }
}
