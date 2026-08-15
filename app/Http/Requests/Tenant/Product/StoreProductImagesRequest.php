<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates product gallery image uploads.
 */
class StoreProductImagesRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('delete')) {
            return [
                'media_ids' => ['required', 'array', 'min:1'],
                'media_ids.*' => ['integer', 'min:1'],
            ];
        }

        return [
            'image' => MediaValidation::image(required: false),
            'images' => ['sometimes', 'array'],
            'images.*' => MediaValidation::image(required: true),
        ];
    }
}
