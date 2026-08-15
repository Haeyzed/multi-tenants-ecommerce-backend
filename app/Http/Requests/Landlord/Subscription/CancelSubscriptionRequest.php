<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Subscription;

use App\Http\Requests\BaseRequest;

/**
 * Validates subscription cancellation payloads.
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
