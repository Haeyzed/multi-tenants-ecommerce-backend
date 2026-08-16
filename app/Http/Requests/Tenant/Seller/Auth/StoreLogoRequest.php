<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Seller\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;

/**
 * Validates seller logo upload.
 */
class StoreLogoRequest extends BaseRequest
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
