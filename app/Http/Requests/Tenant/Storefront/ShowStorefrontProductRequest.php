<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Storefront;

use App\Http\Requests\BaseRequest;

/**
 * Validation for a single storefront product read.
 */
class ShowStorefrontProductRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Anonymous storefront session identifier, from the body or the X-Session-Key header.
     */
    public function sessionKey(): ?string
    {
        $sessionKey = $this->input('session_key') ?? $this->header('X-Session-Key');

        if (! is_string($sessionKey)) {
            return null;
        }

        $sessionKey = trim($sessionKey);

        return $sessionKey === '' ? null : mb_substr($sessionKey, 0, 100);
    }
}
