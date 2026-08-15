<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Brand;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates brand update payloads (JSON or multipart).
 */
class UpdateBrandRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $brandId */
        $brandId = $this->route('brand');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->ignore($brandId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'logo' => MediaValidation::image(required: false),
        ];
    }
}
