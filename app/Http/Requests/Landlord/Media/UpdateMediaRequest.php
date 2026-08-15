<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Media;

use App\Http\Requests\BaseRequest;

/**
 * Validates media metadata updates.
 */
class UpdateMediaRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_properties' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
