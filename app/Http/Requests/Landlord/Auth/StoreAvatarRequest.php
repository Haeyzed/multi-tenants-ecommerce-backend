<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates avatar upload for the authenticated landlord user.
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
