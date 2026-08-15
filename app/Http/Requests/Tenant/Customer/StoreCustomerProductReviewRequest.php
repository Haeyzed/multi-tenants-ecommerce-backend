<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates customer product review creation payloads.
 */
class StoreCustomerProductReviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')],
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => MediaValidation::image(required: true),
        ];
    }
}
