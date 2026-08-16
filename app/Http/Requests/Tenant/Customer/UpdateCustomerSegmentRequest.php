<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Http\Requests\BaseRequest;
use App\Models\Tenant\CustomerSegment;
use Illuminate\Validation\Rule;

/**
 * Validates customer segment update payloads.
 */
class UpdateCustomerSegmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CustomerSegment|int|string|null $segment */
        $segment = $this->route('segment');
        $segmentId = is_object($segment) ? $segment->getKey() : $segment;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('customer_segments', 'name')->ignore($segmentId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'match' => ['sometimes', 'string', Rule::in(['all', 'any'])],
            'conditions' => ['sometimes', 'required', 'array', 'min:1'],
            'conditions.*.type' => ['required_with:conditions', 'string', Rule::enum(CustomerSegmentRule::class)],
            'conditions.*.value' => ['sometimes', 'nullable'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
