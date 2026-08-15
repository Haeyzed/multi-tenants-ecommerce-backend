<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Subscription;

use App\Http\Requests\BaseRequest;

/**
 * Validates tenant payment verification payloads.
 */
class VerifyPaymentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }
}
