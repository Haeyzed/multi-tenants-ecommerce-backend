<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates admin review index query params.
 */
class IndexProductReviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_id' => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')],
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')],
            'status' => ['sometimes', 'nullable', Rule::enum(ProductReviewStatus::class)],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
