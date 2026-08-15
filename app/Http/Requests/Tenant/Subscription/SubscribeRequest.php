<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Subscription;

use App\Http\Requests\BaseRequest;
use App\Models\Landlord\Plan;
use Illuminate\Validation\Rule;

/**
 * Validates tenant subscribe payloads.
 */
class SubscribeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'callback_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
