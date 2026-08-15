<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\World;

use App\Http\Requests\BaseRequest;

/**
 * Validates query parameters for IP geolocation.
 */
class GeolocateRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ip' => ['sometimes', 'nullable', 'ip'],
        ];
    }
}
