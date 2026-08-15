<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\TenantProfile;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates tenant profile cover upload.
 */
class StoreCoverRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cover' => MediaValidation::image(required: true),
        ];
    }
}
