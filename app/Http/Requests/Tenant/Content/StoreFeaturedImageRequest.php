<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Content;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates CMS featured image upload.
 */
class StoreFeaturedImageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'featured_image' => MediaValidation::image(required: true),
        ];
    }
}
