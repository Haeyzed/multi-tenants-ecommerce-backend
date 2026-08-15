<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Category;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates category image upload.
 */
class StoreCategoryImageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => MediaValidation::image(required: true),
        ];
    }
}
