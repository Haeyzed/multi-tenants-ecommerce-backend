<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Subscription;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates plan change payloads.
 */
class ChangePlanRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'callback_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'immediate' => ['sometimes', 'boolean'],
        ];
    }
}
