<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Subscription;

use App\Http\Requests\BaseRequest;

/**
 * Validates tenant subscription cancellation payloads.
 */
class CancelSubscriptionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'immediately' => ['sometimes', 'boolean'],
        ];
    }
}
