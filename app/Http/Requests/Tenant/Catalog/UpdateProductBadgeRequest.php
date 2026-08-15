<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates badge update payloads.
 */
class UpdateProductBadgeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $badgeId */
        $badgeId = $this->route('badge');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_badges', 'name')->ignore($badgeId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('product_badges', 'slug')->ignore($badgeId)],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
