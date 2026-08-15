<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\CollectionType;
use App\Http\Requests\BaseRequest;
use App\Support\Media\MediaValidation;
use Illuminate\Validation\Rule;

/**
 * Validates collection creation payloads.
 */
class StoreProductCollectionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('collections', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::enum(CollectionType::class)],
            'status' => ['sometimes', Rule::enum(CollectionStatus::class)],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'image' => MediaValidation::image(required: false),
        ];
    }
}
