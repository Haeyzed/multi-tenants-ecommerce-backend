<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates badge creation payloads.
 */
class StoreProductBadgeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('product_badges', 'name')],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_badges', 'slug')],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
