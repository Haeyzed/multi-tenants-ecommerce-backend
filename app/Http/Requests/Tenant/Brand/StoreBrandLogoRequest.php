<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Brand;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates brand logo upload.
 */
class StoreBrandLogoRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => MediaValidation::image(required: true),
        ];
    }
}
