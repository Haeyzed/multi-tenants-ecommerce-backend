<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates customer avatar upload.
 */
class StoreAvatarRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => MediaValidation::image(required: true),
        ];
    }
}
